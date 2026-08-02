<?php

namespace Pushword\Core\Command;

use Pushword\Core\Entity\Page;
use Pushword\Core\Query\Condition;
use Pushword\Core\Query\Group;
use Pushword\Core\Query\Search\PageSearchVocabulary;
use Pushword\Core\Query\Search\SearchException;
use Pushword\Core\Query\Search\SearchParser;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Finds the `pages_list` searches on your site that render nothing.
 *
 * The engine cannot warn about them itself. An unrecognised prefix is a tag
 * search, and has to stay one: `type:product` is a namespaced tag carried by
 * real sites, and nothing at parse time tells it from `tags:blog`, a typo for
 * `tag:blog`. Both are well-formed searches for a tag that may or may not exist.
 *
 * Running them settles it. A search matching no page is a fact, not a guess, and
 * it catches strictly more than a stricter parser could: the typo, but also a
 * `slug:` naming a deleted page and an `ancestor:` on a renamed rubric.
 *
 * It reads the searches written in page content, which is where editors write
 * them. A search built in a template from a variable is invisible to it.
 */
#[AsCommand(name: 'pw:pages-list:lint', description: 'Report the pages_list searches in your content that match no page')]
final class PagesListLintCommand
{
    use AgentOutputTrait;

    /** The first argument of a listing call, when it is written out. */
    private const string CALL_PATTERN = '/\b(?:pages_list|draft_list)\s*\(\s*([\'"])(.*?)\1/';

    /** A term that fell through to the tag search and looks like it meant a prefix. */
    private const string PREFIXED_TERM_PATTERN = '/^[a-zA-Z]\w*:/';

    private bool $agentMode = false;

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly SiteRegistry $apps,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Only lint the pages of this host', name: 'host')]
        string $host = '',
        #[Option(description: 'Output format: auto (compact JSON when an AI agent is detected), agent (force JSON), or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->agentMode = $this->isAgentFormat($format);
        $io = new SymfonyStyle($input, $output);

        $pages = '' === $host
            ? $this->pageRepository->findAll()
            : $this->pageRepository->findBy(['host' => $host]);

        $checked = 0;
        $findings = [];

        foreach ($pages as $page) {
            foreach ($this->searchesIn($page) as $search) {
                ++$checked;
                $finding = $this->check($page, $search);

                if (null !== $finding) {
                    $findings[] = $finding;
                }
            }
        }

        return $this->report($io, $output, \count($pages), $checked, $findings);
    }

    /**
     * @return list<string>
     */
    private function searchesIn(Page $page): array
    {
        if (0 === preg_match_all(self::CALL_PATTERN, $page->mainContent, $matches)) {
            return [];
        }

        return array_values(array_unique(array_filter($matches[2], static fn (string $search): bool => '' !== trim($search))));
    }

    /**
     * @return array{page: string, host: string, search: string, reason: string, hint: string}|null
     */
    private function check(Page $page, string $search): ?array
    {
        try {
            $criteria = new SearchParser(new PageSearchVocabulary($page))->parse($search);
        } catch (SearchException $searchException) {
            return $this->finding($page, $search, $searchException->getMessage(), '');
        }

        if ($this->countMatching($page, $criteria) > 0) {
            return null;
        }

        return $this->finding($page, $search, 'matches no page', $this->hint($search));
    }

    private function countMatching(Page $page, Group|Condition $criteria): int
    {
        // The same narrowing pages_list applies on top of the search, so a
        // search reported as dead really does render an empty list.
        $queryBuilder = $this->pageRepository->getPublishedPageQueryBuilder($page->host, $criteria);

        $locale = '' !== $page->locale ? $page->locale : $this->apps->getLocale();
        $this->pageRepository->andLocale($queryBuilder, $locale);
        $this->pageRepository->andNotRedirection($queryBuilder);
        $this->pageRepository->andIndexable($queryBuilder);

        return (int) $queryBuilder->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
    }

    /**
     * A dead search whose term reads like `word:value` is most often a prefix
     * that does not exist. Only said about a search that already matches
     * nothing: on its own, the shape is indistinguishable from a namespaced tag.
     */
    private function hint(string $search): string
    {
        foreach (preg_split('/\s+(?:AND|OR)\s+/', $search) ?: [] as $term) {
            $term = trim($term, " \t()");

            if (1 === preg_match(self::PREFIXED_TERM_PATTERN, $term)) {
                return \sprintf('"%s" is read as a tag name; if you meant a prefix, check its spelling against pages-list.md', $term);
            }
        }

        return '';
    }

    /**
     * @return array{page: string, host: string, search: string, reason: string, hint: string}
     */
    private function finding(Page $page, string $search, string $reason, string $hint): array
    {
        return [
            'page' => $page->slug,
            'host' => $page->host,
            'search' => $search,
            'reason' => $reason,
            'hint' => $hint,
        ];
    }

    /**
     * @param list<array{page: string, host: string, search: string, reason: string, hint: string}> $findings
     */
    private function report(SymfonyStyle $io, OutputInterface $output, int $pages, int $checked, array $findings): int
    {
        if ($this->agentMode) {
            $this->writeAgentJson($output, [
                'tool' => 'pages-list-lint',
                'result' => [] === $findings ? 'passed' : 'failed',
                'pages' => $pages,
                'searches' => $checked,
                'dead' => $findings,
            ]);

            return [] === $findings ? Command::SUCCESS : Command::FAILURE;
        }

        if ([] === $findings) {
            $io->success(\sprintf('%d searches over %d pages, all of them match something.', $checked, $pages));

            return Command::SUCCESS;
        }

        $io->table(
            ['Page', 'Search', 'Why'],
            array_map(
                static fn (array $finding): array => [
                    $finding['host'].'/'.$finding['page'],
                    $finding['search'],
                    $finding['reason'].('' === $finding['hint'] ? '' : "\n".$finding['hint']),
                ],
                $findings,
            ),
        );

        $io->warning(\sprintf('%d of %d searches render nothing.', \count($findings), $checked));

        return Command::FAILURE;
    }
}
