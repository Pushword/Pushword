<?php

namespace Pushword\Core\EventListener;

use Pushword\Core\Service\EditorNotice\TwigErrorMarker;
use Pushword\Core\Service\Markdown\BrokenImageComment;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Turns the invisible pushword degradation markers (broken image, failed Twig
 * block) into visible inline badges — but only for a logged-in ROLE_EDITOR.
 *
 * Visitors keep the invisible comment, which the static generator strips, so the
 * public HTML and the shared render cache stay untouched: the renderers always
 * emit the same role-independent marker (cacheable), and this role-dependent
 * rewrite runs on the final, non-cached response only.
 *
 * Performance: anonymous traffic pays a single str_contains() — the security
 * token is never even loaded unless a marker is actually present in the body.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class EditorNoticeListener
{
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

        if (! \is_string($content) || ! str_contains($content, '<!-- pushword:')) {
            return;
        }

        if (! str_contains($response->headers->get('Content-Type', 'text/html'), 'html')) {
            return;
        }

        if (null === $this->security->getToken() || ! $this->security->isGranted('ROLE_EDITOR')) {
            return;
        }

        $rewritten = $this->rewrite($content);
        if ($rewritten === $content) {
            return;
        }

        $response->setContent($rewritten);
        // Editor-annotated HTML must never land in a shared cache.
        $response->headers->set('Cache-Control', 'private, no-store');
    }

    private function rewrite(string $html): string
    {
        $html = preg_replace_callback(
            BrokenImageComment::PATTERN,
            fn (array $matches): string => $this->badge(
                'warning',
                $this->translator->trans('editorNoticeBrokenImage').' '.htmlspecialchars_decode($matches[1]),
            ),
            $html,
        ) ?? $html;

        return preg_replace_callback(
            TwigErrorMarker::PATTERN,
            fn (array $matches): string => $this->badge(
                'error',
                $this->translator->trans('editorNoticeTwigError').' '.htmlspecialchars_decode($matches[1]),
            ),
            $html,
        ) ?? $html;
    }

    private function badge(string $level, string $text): string
    {
        [$bg, $fg, $border] = 'error' === $level
            ? ['#fee2e2', '#991b1b', '#f87171']
            : ['#fef3c7', '#92400e', '#fbbf24'];

        return '<span data-pw-editor-notice="'.$level.'" style="display:inline-block;margin:2px;padding:2px 8px;'
            .'border:1px solid '.$border.';border-radius:6px;background:'.$bg.';color:'.$fg.';'
            .'font:600 12px/1.4 ui-monospace,SFMono-Regular,monospace;white-space:normal;">'
            .htmlspecialchars($text, \ENT_QUOTES).'</span>';
    }
}
