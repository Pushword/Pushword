<?php

namespace Pushword\Core\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Pushword\Core\EventListener\EditorNoticeListener;
use Pushword\Core\Service\EditorNotice\TwigErrorMarker;
use Pushword\Core\Service\Markdown\BrokenImageComment;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EditorNoticeListenerTest extends TestCase
{
    private function dispatch(
        string $html,
        bool $authenticated,
        bool $isEditor,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
        string $contentType = 'text/html; charset=UTF-8',
    ): Response {
        $security = $this->createMock(Security::class);
        $security->method('getToken')->willReturn($authenticated ? $this->createMock(TokenInterface::class) : null);
        $security->method('isGranted')->willReturn($isEditor);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $response = new Response($html);
        $response->headers->set('Content-Type', $contentType);

        $event = new ResponseEvent(
            self::createStub(HttpKernelInterface::class),
            new Request(),
            $requestType,
            $response,
        );

        (new EditorNoticeListener($security, $translator))($event);

        return $event->getResponse();
    }

    public function testInjectsVisibleBadgesForALoggedInEditor(): void
    {
        $html = '<main>'.BrokenImageComment::for('gone.jpg').TwigErrorMarker::for('Unknown "foo".').'</main>';

        $response = $this->dispatch($html, authenticated: true, isEditor: true);
        $content = (string) $response->getContent();

        self::assertStringNotContainsString('pushword:broken-image', $content);
        self::assertStringNotContainsString('pushword:twig-error', $content);
        self::assertStringContainsString('data-pw-editor-notice="warning"', $content);
        self::assertStringContainsString('data-pw-editor-notice="error"', $content);
        self::assertStringContainsString('gone.jpg', $content);
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    public function testLeavesMarkersInvisibleForAnonymousVisitors(): void
    {
        $html = '<main>'.BrokenImageComment::for('gone.jpg').'</main>';

        $content = (string) $this->dispatch($html, authenticated: false, isEditor: false)->getContent();

        self::assertSame($html, $content);
        self::assertStringNotContainsString('data-pw-editor-notice', $content);
    }

    public function testLeavesMarkersInvisibleForAuthenticatedNonEditors(): void
    {
        $html = '<main>'.TwigErrorMarker::for('boom').'</main>';

        $content = (string) $this->dispatch($html, authenticated: true, isEditor: false)->getContent();

        self::assertSame($html, $content);
    }

    public function testIgnoresSubRequests(): void
    {
        $html = '<main>'.TwigErrorMarker::for('boom').'</main>';

        $content = (string) $this->dispatch($html, authenticated: true, isEditor: true, requestType: HttpKernelInterface::SUB_REQUEST)->getContent();

        self::assertSame($html, $content);
    }

    public function testIgnoresNonHtmlResponses(): void
    {
        $html = '{"marker":"'.TwigErrorMarker::for('boom').'"}';

        $content = (string) $this->dispatch($html, authenticated: true, isEditor: true, contentType: 'application/json')->getContent();

        self::assertSame($html, $content);
    }

    public function testLeavesMarkerlessHtmlUntouched(): void
    {
        $html = '<main><p>All good.</p></main>';

        $content = (string) $this->dispatch($html, authenticated: true, isEditor: true)->getContent();

        self::assertSame($html, $content);
    }
}
