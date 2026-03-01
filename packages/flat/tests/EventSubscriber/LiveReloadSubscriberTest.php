<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Pushword\Flat\EventSubscriber\LiveReloadSubscriber;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class LiveReloadSubscriberTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/live-reload-test-'.uniqid();
        mkdir($this->tempDir.'/public', 0755, true);
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        if (is_dir($this->tempDir)) {
            $fs->remove($this->tempDir);
        }
    }

    public function testGetSubscribedEvents(): void
    {
        $events = LiveReloadSubscriber::getSubscribedEvents();
        self::assertArrayHasKey(KernelEvents::RESPONSE, $events);
    }

    public function testInjectsScriptWhenDebugAndSignalFileExists(): void
    {
        file_put_contents($this->tempDir.'/public/_flat-reload.txt', '12345');

        $subscriber = new LiveReloadSubscriber(true, $this->tempDir);
        $event = $this->createResponseEvent('<html><body><p>Hello</p></body></html>', 'text/html');

        $subscriber->onKernelResponse($event);

        $content = $event->getResponse()->getContent();
        self::assertStringContainsString('_flat-reload.txt', (string) $content);
        self::assertStringContainsString('</body>', (string) $content);
    }

    public function testDoesNotInjectWhenSignalFileMissing(): void
    {
        $subscriber = new LiveReloadSubscriber(true, $this->tempDir);
        $event = $this->createResponseEvent('<html><body><p>Hello</p></body></html>', 'text/html');

        $subscriber->onKernelResponse($event);

        $content = $event->getResponse()->getContent();
        self::assertStringNotContainsString('_flat-reload.txt', (string) $content);
    }

    public function testDoesNotInjectWhenDebugFalse(): void
    {
        file_put_contents($this->tempDir.'/public/_flat-reload.txt', '12345');

        $subscriber = new LiveReloadSubscriber(false, $this->tempDir);
        $event = $this->createResponseEvent('<html><body><p>Hello</p></body></html>', 'text/html');

        $subscriber->onKernelResponse($event);

        $content = $event->getResponse()->getContent();
        self::assertStringNotContainsString('_flat-reload.txt', (string) $content);
    }

    public function testDoesNotInjectForNonHtmlResponse(): void
    {
        file_put_contents($this->tempDir.'/public/_flat-reload.txt', '12345');

        $subscriber = new LiveReloadSubscriber(true, $this->tempDir);
        $event = $this->createResponseEvent('{"key": "value"}', 'application/json');

        $subscriber->onKernelResponse($event);

        $content = $event->getResponse()->getContent();
        self::assertStringNotContainsString('_flat-reload.txt', (string) $content);
    }

    public function testDoesNotInjectForRedirects(): void
    {
        file_put_contents($this->tempDir.'/public/_flat-reload.txt', '12345');

        $subscriber = new LiveReloadSubscriber(true, $this->tempDir);
        $response = new Response('', Response::HTTP_FOUND, ['Location' => '/other']);
        $response->headers->set('Content-Type', 'text/html');

        $event = $this->createResponseEventWithResponse($response);

        $subscriber->onKernelResponse($event);

        $content = $event->getResponse()->getContent();
        self::assertStringNotContainsString('_flat-reload.txt', (string) $content);
    }

    private function createResponseEvent(string $content, string $contentType): ResponseEvent
    {
        $response = new Response($content, Response::HTTP_OK, ['Content-Type' => $contentType]);

        return $this->createResponseEventWithResponse($response);
    }

    private function createResponseEventWithResponse(Response $response): ResponseEvent
    {
        $kernel = self::createStub(HttpKernelInterface::class);
        $request = new Request();

        return new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
