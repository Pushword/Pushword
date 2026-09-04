<?php

namespace Pushword\LinkImprover\Tests;

use PHPUnit\Framework\TestCase;
use Pushword\LinkImprover\EventListener\EditorAutoLinkListener;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EditorAutoLinkListenerTest extends TestCase
{
    private const string PAGE = '<html><head><title>t</title></head><body>'
        .'<p>An <a href="/kiwano" data-auto-link>auto link</a> and an <a href="/melon">editorial one</a>.</p>'
        .'</body></html>';

    private function dispatch(
        string $html,
        bool $authenticated,
        bool $isEditor,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
        string $contentType = 'text/html; charset=UTF-8',
    ): Response {
        $security = self::createStub(Security::class);
        $security->method('getToken')->willReturn($authenticated ? self::createStub(TokenInterface::class) : null);
        $security->method('isGranted')->willReturn($isEditor);

        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $response = new Response($html);
        $response->headers->set('Content-Type', $contentType);

        $event = new ResponseEvent(
            self::createStub(HttpKernelInterface::class),
            new Request(),
            $requestType,
            $response,
        );

        (new EditorAutoLinkListener($security, $translator))($event);

        return $event->getResponse();
    }

    public function testMarksAutoLinksForALoggedInEditor(): void
    {
        $response = $this->dispatch(self::PAGE, authenticated: true, isEditor: true);
        $content = (string) $response->getContent();

        self::assertStringContainsString('<a title="linkImproverEditorAutoLink" href="/kiwano" data-auto-link>', $content);
        self::assertStringContainsString('<style data-pw-auto-link-editor>', $content);
        self::assertStringContainsString('a[data-auto-link][data-auto-link]{', $content);
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    public function testLeavesTheEditorialLinkAlone(): void
    {
        $content = (string) $this->dispatch(self::PAGE, authenticated: true, isEditor: true)->getContent();

        self::assertStringContainsString('<a href="/melon">editorial one</a>', $content);
    }

    public function testTheStyleGoesInTheHead(): void
    {
        $content = (string) $this->dispatch(self::PAGE, authenticated: true, isEditor: true)->getContent();

        self::assertStringContainsString('<style data-pw-auto-link-editor>', $content);
        self::assertLessThan(
            (int) strpos($content, '</head>'),
            (int) strpos($content, '<style data-pw-auto-link-editor>'),
        );
    }

    /** A page without a head still gets its marking, before the closing body. */
    public function testFallsBackToTheBodyWhenThereIsNoHead(): void
    {
        $content = (string) $this->dispatch(
            '<body><p><a href="/kiwano" data-auto-link>auto</a></p></body>',
            authenticated: true,
            isEditor: true,
        )->getContent();

        self::assertStringContainsString('<style data-pw-auto-link-editor>', $content);
        self::assertLessThan(
            (int) strpos($content, '</body>'),
            (int) strpos($content, '<style data-pw-auto-link-editor>'),
        );
    }

    public function testAnAnchorKeepsATitleItAlreadyCarries(): void
    {
        $content = (string) $this->dispatch(
            '<body><a href="/kiwano" title="mine" data-auto-link>auto</a></body>',
            authenticated: true,
            isEditor: true,
        )->getContent();

        self::assertStringContainsString('<a href="/kiwano" title="mine" data-auto-link>', $content);
        self::assertStringNotContainsString('linkImproverEditorAutoLink', $content);
    }

    /** Whatever the Html* filters left around the attribute, the tag is still found. */
    public function testMatchesTheAttributeInAnyPosition(): void
    {
        $content = (string) $this->dispatch(
            '<body><a class="c" data-auto-link href="/kiwano" rel="noopener">auto</a></body>',
            authenticated: true,
            isEditor: true,
        )->getContent();

        self::assertStringContainsString('<a title="linkImproverEditorAutoLink" class="c" data-auto-link', $content);
    }

    public function testLeavesThePageUntouchedForAnonymousVisitors(): void
    {
        $response = $this->dispatch(self::PAGE, authenticated: false, isEditor: false);

        self::assertSame(self::PAGE, (string) $response->getContent());
        self::assertFalse($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testLeavesThePageUntouchedForANonEditor(): void
    {
        $content = (string) $this->dispatch(self::PAGE, authenticated: true, isEditor: false)->getContent();

        self::assertSame(self::PAGE, $content);
    }

    public function testIgnoresAPageWithoutAnyAutoLink(): void
    {
        $html = '<html><head></head><body><a href="/melon">editorial</a></body></html>';

        self::assertSame($html, (string) $this->dispatch($html, authenticated: true, isEditor: true)->getContent());
    }

    public function testIgnoresSubRequests(): void
    {
        $content = (string) $this->dispatch(
            self::PAGE,
            authenticated: true,
            isEditor: true,
            requestType: HttpKernelInterface::SUB_REQUEST,
        )->getContent();

        self::assertSame(self::PAGE, $content);
    }

    public function testIgnoresNonHtmlResponses(): void
    {
        $content = (string) $this->dispatch(
            self::PAGE,
            authenticated: true,
            isEditor: true,
            contentType: 'application/json',
        )->getContent();

        self::assertSame(self::PAGE, $content);
    }
}
