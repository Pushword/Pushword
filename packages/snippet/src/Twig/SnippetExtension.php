<?php

namespace Pushword\Snippet\Twig;

use Pushword\Core\Content\ContentPipelineFactory;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Snippet\Entity\Snippet;
use Pushword\Snippet\Registry\SnippetRegistry;
use Pushword\Snippet\Repository\SnippetRepository;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

final readonly class SnippetExtension
{
    public function __construct(
        private SnippetRegistry $registry,
        private SnippetRepository $snippetRepository,
        private ContentPipelineFactory $pipelineFactory,
        private SiteRegistry $siteRegistry,
        private Environment $twig,
        private MarkdownParser $markdownParser,
    ) {
    }

    /**
     * Snippet catalogue for the block editor: dev components (with their schema)
     * merged with the editor-owned content snippets of the given host (no schema,
     * free-form params). A content snippet never shadows a component of the same name.
     *
     * @return array<string, array{label: string, schema: array<string, array<string, mixed>>}>
     */
    #[AsTwigFunction('snippet_editor_definitions')]
    public function getEditorDefinitions(string $host = ''): array
    {
        $definitions = $this->registry->getDefinitions();

        $host = '' !== $host ? $host : $this->currentHost();
        foreach ($this->snippetRepository->findByHost($host) as $snippet) {
            $definitions[$snippet->getSlug()] ??= [
                'label' => $snippet->getName(),
                'schema' => [],
            ];
        }

        return $definitions;
    }

    /**
     * Render a reusable snippet by name. Resolves a dev-registered component
     * first, then falls back to an editor-owned content snippet for the current
     * host. Returns an empty string when neither exists.
     *
     * @param array<string, mixed> $params
     */
    #[AsTwigFunction('snippet', isSafe: ['html'])]
    public function renderSnippet(string $name, array $params = []): string
    {
        if ($this->registry->hasComponent($name)) {
            return $this->renderComponent($name, $params);
        }

        $snippet = $this->snippetRepository->findOneBySlugAndHost($name, $this->currentHost());

        return null !== $snippet ? $this->renderContent($snippet, $params) : '';
    }

    /**
     * @param array<string, mixed> $params
     */
    private function renderComponent(string $name, array $params): string
    {
        $component = $this->registry->getComponent($name);
        $template = $this->registry->getTemplate($name);
        \assert(null !== $component && null !== $template);

        $params = $component->prepareParams($params);

        return $this->twig->render($template, [
            'params' => $params,
            'page' => $this->siteRegistry->getCurrentPage(),
        ] + $params);
    }

    /**
     * Render an editor-owned content snippet through the same filter pipeline as
     * a page (Markdown → multisite links → ShowMore…). Twig is applied here, with
     * the params in context, so it is excluded from the downstream filter chain.
     *
     * @param array<string, mixed> $params
     */
    private function renderContent(Snippet $snippet, array $params): string
    {
        $page = $this->siteRegistry->getCurrentPage();

        $content = $this->twig->createTemplate($snippet->getContent())->render([
            'page' => $page,
            'snippet' => $snippet,
            'params' => $params,
        ] + $params);

        if (null === $page) {
            return $this->markdownParser->transform($content);
        }

        $value = $this->pipelineFactory->get($page)->applyFilters($content, $this->contentFilters($snippet->host), 'mainContent');

        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * Main-content filter chain minus `twig` (already applied with params).
     *
     * @return string[]
     */
    private function contentFilters(string $host): array
    {
        $filters = $this->normalizeFilters($this->siteRegistry->get($host)->getFilters()['main_content'] ?? 'markdown');

        return array_values(array_filter($filters, static fn (string $filter): bool => 'twig' !== $filter));
    }

    /**
     * Filters config can be a comma-separated string or an array (per site config).
     *
     * @return string[]
     */
    private function normalizeFilters(mixed $filters): array
    {
        if (\is_array($filters)) {
            return array_map(static fn (mixed $filter): string => \is_scalar($filter) ? (string) $filter : '', $filters);
        }

        return \is_scalar($filters) ? explode(',', (string) $filters) : ['markdown'];
    }

    private function currentHost(): string
    {
        $page = $this->siteRegistry->getCurrentPage();

        return null !== $page ? $page->host : ($this->siteRegistry->getMainHost() ?? '');
    }
}
