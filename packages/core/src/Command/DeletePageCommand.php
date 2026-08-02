<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: DeletePageCommand::NAME, description: 'Delete every page carrying a given tag')]
final readonly class DeletePageCommand
{
    use AgentOutputTrait;

    public const string NAME = 'pw:page:delete';

    public function __construct(private EntityManagerInterface $em, private PageRepository $pageRepo)
    {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Delete every page carrying this tag', name: 'tag')]
        string $tag = '',
        #[Option(description: 'Delete without asking for confirmation', name: 'force')]
        bool $force = false,
        #[Option(description: 'auto|agent|text', name: 'format')]
        string $format = 'auto',
    ): int {
        $agentMode = $this->isAgentFormat($format);
        $io = new SymfonyStyle($input, $output);

        if ('' === $tag) {
            if ($agentMode) {
                $this->writeAgentJson($output, ['tool' => self::NAME, 'result' => 'failed', 'error' => '--tag is required']);
            } else {
                $io->error('--tag is required. Example: pw:page:delete --tag=demo');
            }

            return Command::INVALID;
        }

        // Tags live in a JSON column: filtering here keeps the command working the same
        // on SQLite and MariaDB.
        $pages = array_values(array_filter(
            $this->pageRepo->findAll(),
            static fn (Page $page): bool => \in_array($tag, $page->getTagList(), true)
        ));
        $slugs = array_map(static fn (Page $page): string => $page->slug, $pages);
        $count = \count($pages);

        if ([] === $pages) {
            if ($agentMode) {
                $this->writeAgentJson($output, ['tool' => self::NAME, 'result' => 'done', 'tag' => $tag, 'deleted' => 0, 'slugs' => []]);
            } else {
                $io->success(\sprintf('No page tagged `%s`.', $tag));
            }

            return Command::SUCCESS;
        }

        if (! $force) {
            if (! $input->isInteractive()) {
                if ($agentMode) {
                    $this->writeAgentJson($output, ['tool' => self::NAME, 'result' => 'blocked', 'tag' => $tag, 'matched' => $count, 'slugs' => $slugs, 'error' => 'pass --force to delete without a terminal']);
                } else {
                    $io->error('Pass --force to delete without a terminal.');
                }

                return Command::FAILURE;
            }

            $io->listing($slugs);
            if (! $io->confirm(\sprintf('Delete %d page(s) tagged `%s`?', $count, $tag), false)) {
                return Command::SUCCESS;
            }
        }

        foreach ($pages as $page) {
            $this->em->remove($page);
        }

        $this->em->flush();

        if ($agentMode) {
            $this->writeAgentJson($output, ['tool' => self::NAME, 'result' => 'done', 'tag' => $tag, 'deleted' => $count, 'slugs' => $slugs]);
        } else {
            $io->success(\sprintf('Deleted %d page(s) tagged `%s`.', $count, $tag));
        }

        return Command::SUCCESS;
    }
}
