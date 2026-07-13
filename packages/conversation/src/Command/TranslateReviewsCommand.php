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
    /**
     * Reviews are processed in batches so that (1) a crash mid-run persists every
     * completed batch instead of losing the whole run at a single end-of-run flush,
     * and (2) the Doctrine identity map stays bounded instead of holding every review
     * (large catalogues would otherwise grow memory linearly). We detach() each
     * processed review rather than clear() the whole EntityManager: TranslationUsageTracker
     * keeps a managed TranslationUsage entity cached, and a global clear() would detach it
     * and break per-service quota accounting.
     */
    private const int BATCH_SIZE = 50;

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

            $reviewIds = $this->getReviewIds($currentHost);

            if ([] === $reviewIds) {
                $io->info(\sprintf('[%s] No reviews found to translate.', $currentHost));

                continue;
            }

            $io->section(\sprintf('[%s] Translating %d reviews to: %s', $currentHost, \count($reviewIds), implode(', ', $targetLocales)));

            if ($dryRun) {
                $io->note('Dry run mode - no changes will be made.');
            }

            // Process in batches: flush + detach after each so a crash keeps prior work
            // and memory stays bounded (see BATCH_SIZE docblock).
            foreach (array_chunk($reviewIds, self::BATCH_SIZE) as $chunk) {
                /** @var Review[] $reviews */
                $reviews = $this->entityManager->getRepository(Review::class)->findBy(['id' => $chunk]);

                foreach ($reviews as $review) {
                    $outcome = $this->processReview($review, $targetLocales, $force, $dryRun, $io);
                    $successCount += $outcome['success'];
                    $errorCount += $outcome['failed'];
                    $skippedCount += $outcome['skipped'];

                    // Only throttle when we actually hit the translation API. Reviews that are
                    // skipped (locale already known and translation already present) cost nothing,
                    // so a daily run over an already-translated catalogue stays fast instead of
                    // sleeping once per review — while a large first run is still rate-limited.
                    if ($outcome['hitApi'] && $delay > 0) {
                        sleep($delay);
                    }
                }

                if (! $dryRun) {
                    $this->translationService->flush();
                }

                foreach ($reviews as $review) {
                    $this->entityManager->detach($review);
                }
            }
        }

        if (! $dryRun) {
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
     * @param string[] $targetLocales
     *
     * @return array{hitApi: bool, success: int, failed: int, skipped: int} per-review tally the
     *                                                                      caller aggregates. hitApi is true when a translation-API request was issued (language
     *                                                                      detection or a non-skipped translation), so the caller throttles only then.
     */
    private function processReview(
        Review $review,
        array $targetLocales,
        bool $force,
        bool $dryRun,
        SymfonyStyle $io,
    ): array {
        $previewText = mb_substr($review->getTitle() ?: $review->getContent(), 0, 50);
        $sourceLocale = $review->locale;

        if (null === $sourceLocale || '' === $sourceLocale) {
            $textToDetect = $review->getTitle().' '.$review->getContent();
            $detectedLocale = $this->translationManager->detectLanguage($textToDetect);

            // Language detection is itself a translation-API request, so it counts as a hit.
            if (null === $detectedLocale) {
                $io->warning(\sprintf('Review #%d: could not detect language, skipping.', (int) $review->id));

                return ['hitApi' => true, 'success' => 0, 'failed' => 0, 'skipped' => 1];
            }

            $sourceLocale = $detectedLocale;
            $review->locale = $detectedLocale;
            $io->writeln(\sprintf(
                '  Processing review #%d (detected: <comment>%s</comment>): "%s"...',
                (int) $review->id,
                $sourceLocale,
                $previewText,
            ));
            $hitApi = true;
        } else {
            $io->writeln(\sprintf(
                '  Processing review #%d (%s): "%s"...',
                (int) $review->id,
                $sourceLocale,
                $previewText,
            ));
            $hitApi = false;
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

            return ['hitApi' => $hitApi, 'success' => 0, 'failed' => 0, 'skipped' => 0];
        }

        $results = $this->translationService->translateReview($review, $targetLocales, $force);

        $success = 0;
        $failed = 0;
        $skipped = 0;
        foreach ($results as $targetLocale => $status) {
            if (ReviewTranslationService::SKIPPED === $status) {
                $io->writeln(\sprintf('    -> %s: <comment>skipped</comment>', $targetLocale));
                ++$skipped;
            } elseif (str_starts_with($status, ReviewTranslationService::FAILED)) {
                $hitApi = true;
                $errorDetail = substr($status, \strlen(ReviewTranslationService::FAILED) + 2) ?: '';
                $io->writeln(\sprintf('    -> %s: <error>FAILED</error>%s', $targetLocale, '' !== $errorDetail ? ' ('.$errorDetail.')' : ''));
                ++$failed;
            } else {
                $hitApi = true;
                $io->writeln(\sprintf('    -> %s: <info>OK</info> (%s)', $targetLocale, $status));
                ++$success;
            }
        }

        $this->translationService->persist($review);

        return ['hitApi' => $hitApi, 'success' => $success, 'failed' => $failed, 'skipped' => $skipped];
    }

    /**
     * @return list<int>
     */
    private function getReviewIds(?string $host): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('r.id')
            ->from(Review::class, 'r')
            ->andWhere('r.customProperties LIKE :ratingFilter')
            ->setParameter('ratingFilter', '%"rating":%');

        if (null !== $host && '' !== $host) {
            $qb->andWhere('r.host = :host')
                ->setParameter('host', $host);
        }

        /** @var list<array{id: int|string}> $rows */
        $rows = $qb->getQuery()->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }
}
