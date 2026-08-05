<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Psr\Log\LoggerInterface;
use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\EditorNotice\TwigErrorMarker;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsFilter]
class Twig implements FilterInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        assert(is_scalar($propertyValue));

        return $this->render((string) $propertyValue, $page);
    }

    protected function render(string $string, Page $page): string
    {
        // Only `{{`, `{%` and `{#` open a Twig token; a string without them renders
        // verbatim, so skip the whole createTemplate/render round-trip. Markdown
        // attribute syntax (`{id=…}`, `{.class}`) lands here on every block.
        if (! str_contains($string, '{{') && ! str_contains($string, '{%') && ! str_contains($string, '{#')) {
            return $string;
        }

        try {
            return $this->twig->createTemplate($string)->render(['page' => $page]);
        } catch (LoaderError|RuntimeError|SyntaxError $twigError) {
            // A malformed `{{ … }}` typed by an editor must not 500 the whole page.
            // Degrade to an invisible marker (scanner reports it, editors see a badge).
            // LoaderError included: `pages_list(view: 'foo')` names a template by
            // convention, so a renamed variant — or a page flat-synced to a host that
            // lacks it — reaches here as a missing template, not as a syntax error.
            $this->logger->warning('Twig rendering failed in page content: {message}', [
                'message' => $twigError->getRawMessage(),
                'slug' => $page->slug,
                'host' => $page->host,
            ]);

            return TwigErrorMarker::for($twigError->getRawMessage());
        }
    }
}
