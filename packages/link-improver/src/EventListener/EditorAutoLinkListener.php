<?php

namespace Pushword\LinkImprover\EventListener;

use Pushword\LinkImprover\LinkImprover;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Automatic linking earns its bad reputation when it is invisible. For a
 * logged-in ROLE_EDITOR, this marks every `data-auto-link` anchor on the public
 * page: a dashed underline in its own colour, and a title saying where the link
 * came from.
 *
 * Same contract as {@see \Pushword\Core\EventListener\EditorNoticeListener}: the
 * renderers emit one role-independent attribute (cacheable), this role-dependent
 * pass runs on the final response only, and an annotated response never lands in
 * a shared cache. Anonymous traffic pays a single str_contains().
 *
 * The marking is decoration, never reflow: an editor has to see the page the way
 * a visitor reads it, so nothing here inserts a glyph or moves a word.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class EditorAutoLinkListener
{
    /**
     * Distinct from the amber and red the notice badges use — an auto link is
     * information, not a problem to fix.
     */
    private const string COLOR = '#6366f1';

    public function __construct(
        private Security $security,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $content = $response->getContent();

        if (! \is_string($content) || ! str_contains($content, LinkImprover::ADDED_LINK_ATTRIBUTE)) {
            return;
        }

        if (! str_contains($response->headers->get('Content-Type', 'text/html'), 'html')) {
            return;
        }

        if (null === $this->security->getToken() || ! $this->security->isGranted('ROLE_EDITOR')) {
            return;
        }

        $rewritten = $this->style($this->annotate($content));
        if ($rewritten === $content) {
            return;
        }

        $response->setContent($rewritten);
        // Editor-annotated HTML must never land in a shared cache.
        $response->headers->set('Cache-Control', 'private, no-store');
    }

    /**
     * Titles every auto link. The attribute order is whatever the Html* filters
     * behind the improver left, so the tag is matched rather than reconstructed,
     * and an anchor that already carries a title keeps it.
     */
    private function annotate(string $html): string
    {
        $title = htmlspecialchars($this->translator->trans('linkImproverEditorAutoLink'), \ENT_QUOTES);

        return preg_replace_callback(
            '/<a\b[^>]*\b'.preg_quote(LinkImprover::ADDED_LINK_ATTRIBUTE, '/').'\b[^>]*>/i',
            static fn (array $matches): string => str_contains(strtolower($matches[0]), 'title=')
                ? $matches[0]
                : '<a title="'.$title.'"'.substr($matches[0], \strlen('<a')),
            $html,
        ) ?? $html;
    }

    private function style(string $html): string
    {
        $style = '<style data-pw-auto-link-editor>'
            .'a['.LinkImprover::ADDED_LINK_ATTRIBUTE.']{'
            .'text-decoration-line:underline;text-decoration-style:dashed;'
            .'text-decoration-color:'.self::COLOR.';text-underline-offset:.18em;'
            .'text-decoration-thickness:from-font;}'
            .'a['.LinkImprover::ADDED_LINK_ATTRIBUTE.']:hover{'
            .'background-color:color-mix(in srgb,'.self::COLOR.' 14%,transparent);'
            .'border-radius:2px;}'
            .'</style>';

        foreach (['</head>', '</body>'] as $anchor) {
            $at = stripos($html, $anchor);
            if (false !== $at) {
                return substr_replace($html, $style, $at, 0);
            }
        }

        return $html;
    }
}
