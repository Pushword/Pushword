<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Component\EntityFilter\Filter\MarkdownProtectCodeBlock;
use Pushword\Core\Content\ShowMoreMarkers;
use Pushword\Core\Repository\PageRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rewrite the legacy `<!--start-show-more-->` pairs as `{{ startShowMore() }}`.
 *
 * Both spellings render the same, so this is never required — it is what makes
 * the blocks editable: the call carries an id and a class, the comment carries
 * nothing, and only a converted pair can be given an anchor in the block editor.
 * Markers are also put on lines of their own, which is what the editor needs to
 * see them as blocks rather than as part of the paragraph above.
 */
#[AsCommand(name: ShowMoreConvertCommand::NAME, description: 'Rewrite the legacy <!--start-show-more--> pairs as {{ startShowMore() }}')]
final readonly class ShowMoreConvertCommand
{
    use AgentOutputTrait;

    public const string NAME = 'pw:show-more:convert';

    public function __construct(
        private PageRepository $pageRepo,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Show what would change without writing', name: 'dry-run')]
        bool $dryRun = false,
        #[Option(description: 'Convert only the pages of this host', name: 'host')]
        string $host = '',
        #[Option(description: 'auto|agent|text', name: 'format')]
        string $format = 'auto',
    ): int {
        $agentMode = $this->isAgentFormat($format);
        $io = new SymfonyStyle($input, $output);

        $pages = '' === $host ? $this->pageRepo->findAll() : $this->pageRepo->findBy(['host' => $host]);

        $converted = [];
        $pairs = 0;
        $orphans = 0;

        foreach ($pages as $page) {
            $body = $page->mainContent;
            if (! str_contains($body, ShowMoreMarkers::START) && ! str_contains($body, ShowMoreMarkers::END)) {
                continue;
            }

            [$rewritten, $pairCount, $orphanCount] = $this->rewrite($body);
            $orphans += $orphanCount;

            if ($rewritten === $body) {
                continue;
            }

            $pairs += $pairCount;
            $converted[] = $page->host.'/'.$page->slug;

            if (! $dryRun) {
                $page->mainContent = $rewritten;
            }
        }

        if (! $dryRun && [] !== $converted) {
            $this->em->flush();
        }

        if ($agentMode) {
            $this->writeAgentJson($output, [
                'tool' => self::NAME,
                'result' => 'done',
                'dryRun' => $dryRun,
                'pages' => \count($converted),
                'pairs' => $pairs,
                'orphanMarkers' => $orphans,
                'slugs' => $converted,
            ]);

            return Command::SUCCESS;
        }

        if ([] === $converted) {
            $io->success('No legacy show-more pair left to convert.');
        } else {
            $io->listing($converted);
            $io->success(\sprintf(
                '%d pair(s) over %d page(s)%s.',
                $pairs,
                \count($converted),
                $dryRun ? ' would be converted (dry-run)' : ' converted',
            ));
        }

        if ($orphans > 0) {
            $io->warning(\sprintf('%d marker(s) left as-is: nothing to pair them with.', $orphans));
        }

        return Command::SUCCESS;
    }

    /**
     * @return array{string, int, int} the rewritten body, the pairs it converted, and
     *                                 the markers it had to leave for want of a partner
     */
    private function rewrite(string $body): array
    {
        // Markers a page only talks about, inside a fence, are not markers.
        $codeBlockProtector = new MarkdownProtectCodeBlock();
        $lines = explode("\n", $codeBlockProtector->protect($body));
        $markers = ShowMoreMarkers::pair($lines);
        $orphans = ShowMoreMarkers::countLines($lines) - \count($markers);

        if ([] === $markers) {
            return [$body, 0, $orphans];
        }

        $rewritten = [];

        foreach ($lines as $index => $line) {
            if (! isset($markers[$index])) {
                $rewritten[] = $line;

                continue;
            }

            // On a line of its own, blank line on each side: that is what makes it
            // a block the editor can show, rather than part of the text above.
            if ([] !== $rewritten && '' !== trim(end($rewritten))) {
                $rewritten[] = '';
            }

            $rewritten[] = $markers[$index] ? '{{ startShowMore() }}' : '{{ endShowMore() }}';

            if ('' !== trim($lines[$index + 1] ?? '')) {
                $rewritten[] = '';
            }
        }

        return [
            $codeBlockProtector->restoreString(implode("\n", $rewritten)),
            \intdiv(\count($markers), 2),
            $orphans,
        ];
    }
}
