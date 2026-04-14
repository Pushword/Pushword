<?php

namespace Pushword\Conversation\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Conversation\Entity\Review;
use Pushword\Conversation\Translation\ReviewTranslationService;
use Pushword\Conversation\Translation\TranslationManager;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pw:conversation:translate-reviews',
    description: 'Translate reviews to specified locales using AI translation services.',
)]
final readonly class TranslateReviewsCommand
{
    public function __construct(
        private SiteRegistry $apps,
        private EntityManagerInterface $entityManager,
        private ReviewTranslationService $translationService,
        private TranslationManager $translationManager,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Filter by host', name: 'host', shortcut: 'H')]
        ?string $host = null,
        #[Option(description: 'Target locale(s) to generate (comma-separated)', name: 'locale', shortcut: 'l')]
        ?string $locale = null,
        #[Option(description: 'Re-translate even if translation exists', name: 'force', shortcut: 'f')]
        bool $force = false,
        #[Option(description: 'Show what would be translated without making changes', name: 'dry-run')]
        bool $dryRun = false,
        #[Option(description: 'Delay in seconds between API requests (rate limiting)', name: 'delay', shortcut: 'd')]
        int $delay = 1,
    ): int {
        $io = new SymfonyStyle($input, $output);

        $targetLocales = null !== $locale ? array_map(trim(...), explode(',', $locale)) : [];
        if ([] === $targetLocales) {
            $io->error('You must specify at least one target locale with --locale option.');

            return Command::FAILURE;
        }

        $hosts = null !== $host ? [$host] : $this->apps->getHosts();
        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        foreach ($hosts as $currentHost) {
            $this->apps->switchSite($currentHost);

            if (! $this->translationManager->hasAvailableTranslator()) {
                if ($this->translationManager->hasConfiguredTranslator() && $this->translationManager->isMonthlyLimitExceeded()) {
                    $io->error(\sprintf('[%s] All translation services have exceeded their monthly character limits.', $currentHost));
                } else {
                    $io->error(\sprintf('[%s] No translation service is configured.', $currentHost));
                }

                continue;
            }

            $reviews = $this->getReviews($currentHost);

            if ([] === $reviews) {
                $io->info(\sprintf('[%s] No reviews found to translate.', $currentHost));

                continue;
            }

            $io->section(\sprintf('[%s] Translating %d reviews to: %s', $currentHost, \count($reviews), implode(', ', $targetLocales)));

            if ($dryRun) {
                $io->note('Dry run mode - no changes will be made.');
            }

            foreach ($reviews as $review) {
                $previewText = mb_substr($review->getTitle() ?: $review->getContent(), 0, 50);
                $sourceLocale = $review->locale;

                if (null === $sourceLocale || '' === $sourceLocale) {
                    $textToDetect = $review->getTitle().' '.$review->getContent();
                    $detectedLocale = $this->translationManager->detectLanguage($textToDetect);

                    if (null === $detectedLocale) {
                        $io->warning(\sprintf('Review #%d: could not detect language, skipping.', (int) $review->id));
                        ++$skippedCount;

                        continue;
                    }

                    $sourceLocale = $detectedLocale;
                    $review->locale = $detectedLocale;
                    $io->writeln(\sprintf(
                        '  Processing review #%d (detected: <comment>%s</comment>): "%s"...',
                        (int) $review->id,
                        $sourceLocale,
                        $previewText,
                    ));
                } else {
                    $io->writeln(\sprintf(
                        '  Processing review #%d (%s): "%s"...',
                        (int) $review->id,
                        $sourceLocale,
                        $previewText,
                    ));
                }

                if ($dryRun) {
                    foreach ($targetLocales as $targetLocale) {
                        if ($targetLocale === $sourceLocale) {
                            continue;
                        }

                        $exists = $review->hasTranslation($targetLocale);
                        $action = $exists && ! $force ? 'skip (exists)' : 'would translate';
                        $io->writeln(\sprintf('    -> %s: %s', $targetLocale, $action));
                    }

                    continue;
                }

                $results = $this->translationService->translateReview($review, $targetLocales, $force);

                foreach ($results as $targetLocale => $status) {
                    if (ReviewTranslationService::SKIPPED === $status) {
                        $io->writeln(\sprintf('    -> %s: <comment>skipped</comment>', $targetLocale));
                        ++$skippedCount;
                    } elseif (str_starts_with($status, ReviewTranslationService::FAILED)) {
                        $errorDetail = substr($status, \strlen(ReviewTranslationService::FAILED) + 2) ?: '';
                        $io->writeln(\sprintf('    -> %s: <error>FAILED</error>%s', $targetLocale, '' !== $errorDetail ? ' ('.$errorDetail.')' : ''));
                        ++$errorCount;
                    } else {
                        $io->writeln(\sprintf('    -> %s: <info>OK</info> (%s)', $targetLocale, $status));
                        ++$successCount;
                    }
                }

                $this->translationService->persist($review);

                if ($delay > 0) {
                    sleep($delay);
                }
            }
        }

        if (! $dryRun) {
            $this->translationService->flush();

            $io->success(\sprintf(
                'Translation complete: %d successful, %d failed, %d skipped.',
                $successCount,
                $errorCount,
                $skippedCount,
            ));
        }

        return 0 === $errorCount ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return Review[]
     */
    private function getReviews(?string $host): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(Review::class, 'r')
            ->andWhere('r.customProperties LIKE :ratingFilter')
            ->setParameter('ratingFilter', '%"rating":%');

        if (null !== $host && '' !== $host) {
            $qb->andWhere('r.host = :host')
                ->setParameter('host', $host);
        }

        return $qb->getQuery()->getResult();
    }
}
