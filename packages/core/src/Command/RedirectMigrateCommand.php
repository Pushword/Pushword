<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Service\RedirectFromResolver;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Fold legacy internal phantom redirects (`Location: /target`) into the destination page's
 * redirectFrom column and delete the phantom pages. Database-level and flat-independent:
 * the single migration path whether or not the site uses flat sync.
 */
#[AsCommand(name: 'pw:redirect:migrate', description: 'Migrate internal phantom redirects into destination pages redirectFrom')]
final readonly class RedirectMigrateCommand
{
    public function __construct(
        private EntityManagerInterface $em,
        private PageRepository $pageRepo,
        private RedirectFromResolver $resolver,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Limit migration to a single host', name: 'host')]
        ?string $host = null,
        #[Option(description: 'Preview changes without writing', name: 'dry-run')]
        bool $dryRun = false,
    ): int {
        $hosts = null !== $host ? [$host] : $this->collectHosts();

        $migrated = 0;
        $deleted = 0;

        foreach ($hosts as $currentHost) {
            $pages = $this->pageRepo->findByHost($currentHost);
            $bySlug = [];
            foreach ($pages as $page) {
                $bySlug[$page->getSlug()] = $page;
            }

            $result = $this->resolver->resolve($pages);

            foreach ($result['reverse'] as $destSlug => $map) {
                $destination = $bySlug[$destSlug] ?? null;
                if (null === $destination) {
                    continue;
                }

                foreach ($map as $from => $code) {
                    $destination->addRedirectFrom($from, $code);
                    ++$migrated;
                    $output->writeln(\sprintf('%s: /%s → /%s (%d)', $currentHost, $from, $destSlug, $code));
                }
            }

            foreach (array_keys($result['foldedSlugs']) as $slug) {
                $phantom = $bySlug[$slug] ?? null;
                if (null !== $phantom) {
                    $this->em->remove($phantom);
                    ++$deleted;
                }
            }
        }

        if ($dryRun) {
            $output->writeln(\sprintf('<comment>Dry run: %d redirect(s) would be folded, %d phantom page(s) removed.</comment>', $migrated, $deleted));

            return Command::SUCCESS;
        }

        $this->em->flush();
        $output->writeln(\sprintf('<info>Folded %d redirect(s), removed %d phantom page(s).</info>', $migrated, $deleted));

        return Command::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function collectHosts(): array
    {
        $meta = $this->em->getClassMetadata(Page::class);
        $hostCol = $meta->getColumnName('host');
        $table = $meta->getTableName();

        /** @var list<array{host: string}> $rows */
        $rows = $this->em->getConnection()->fetchAllAssociative(
            sprintf('SELECT DISTINCT %s AS host FROM %s', $hostCol, $table)
        );

        return array_map(static fn (array $row): string => $row['host'], $rows);
    }
}
