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

    /**
     * A tint behind the anchor, not a change to how the link is underlined: a
     * theme draws its links as it likes — the stock Pushword one uses a
     * `border-bottom` and sets `text-decoration: none` at a specificity no
     * injected rule should have to outbid — so anything built on the link's own
     * decoration is invisible on some sites and doubled on others. Background
     * and box-shadow are properties a link theme has no reason to claim, and
     * neither moves a single word.
     */
    private function style(string $html): string
    {
        // Doubled attribute: enough specificity to survive a themed `a` rule
        // without reaching for !important.
        $selector = 'a['.LinkImprover::ADDED_LINK_ATTRIBUTE.']['.LinkImprover::ADDED_LINK_ATTRIBUTE.']';
        $tint = static fn (int $percent): string => 'background-color:color-mix(in srgb,'.self::COLOR.' '.$percent.'%,transparent);'
            .'box-shadow:0 0 0 .12em color-mix(in srgb,'.self::COLOR.' '.$percent.'%,transparent);';

        $style = '<style data-pw-auto-link-editor>'
            .$selector.'{'.$tint(13).'border-radius:.15em;}'
            .$selector.':hover{'.$tint(28).'}'
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
