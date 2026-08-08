<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Content\ShowMoreMarkers;
use Pushword\Core\Entity\Page;
use Pushword\Core\Twig\ShowMore as ShowMoreRenderer;

/**
 * The legacy `<!--start-show-more-->` / `<!--end-show-more-->` markers, expanded
 * into the wrapper `{{ startShowMore() }}` produces — same renderer, same ids.
 * Kept for the bodies written before the Twig call existed; `pw:show-more:convert`
 * rewrites them.
 *
 * Markers are paired on a stack, so nested blocks close in the right order and a
 * marker left without a partner stays in the body, where it renders as the HTML
 * comment it is — invisibly. Anything else lets one stray `<!--end-show-more-->`
 * decide how everything above it is displayed.
 */
#[AsFilter]
final readonly class ShowMore implements FilterInterface
{
    public function __construct(
        private ShowMoreRenderer $renderer,
    ) {
    }

    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        assert(is_scalar($propertyValue));

        return $this->expandMarkers((string) $propertyValue, $page);
    }

    private function expandMarkers(string $body, Page $page): string
    {
        if (! str_contains($body, ShowMoreMarkers::START)) {
            return $body;
        }

        // A body documenting the syntax holds the markers inside a fence; the
        // renderer has no business there.
        $codeBlockProtector = new MarkdownProtectCodeBlock();
        $lines = explode("\n", $codeBlockProtector->protect($body));

        $markers = ShowMoreMarkers::pair($lines);
        if ([] === $markers) {
            return $body;
        }

        foreach ($markers as $index => $isStart) {
            $html = $isStart
                ? $this->renderer->renderStart(null, '', $page)
                : $this->renderer->renderEnd(null, null, $page);
            // Surrounded by blank lines: the wrapper is an HTML block, and with no
            // blank line after its opening tags CommonMark reads the content it
            // wraps as raw HTML instead of rendering it.
            $lines[$index] = "\n".trim($html)."\n";
        }

        return $codeBlockProtector->restoreString(implode("\n", $lines));
    }
}
