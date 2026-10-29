<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\EventListener;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\EventListener\MediaNotFoundListener;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

final class MediaNotFoundListenerTest extends WebTestCase
{
    /** @return iterable<string, array{string, string, bool}> */
    public static function mediaPaths(): iterable
    {
        yield 'original' => ['media', '/media/missing.jpg', true];
        yield 'variant' => ['media', '/media/md/missing.webp', true];
        yield 'directory' => ['media', '/media', true];
        yield 'directory slash' => ['media', '/media/', true];
        yield 'malformed' => ['media', '/media/md/broken%20image.webp', true];
        yield 'encoded directory' => ['media', '/med%69a/missing.jpg', true];
        yield 'nested configuration' => ['assets/uploads', '/assets/uploads/missing.jpg', true];
        yield 'trimmed configuration' => ['/assets/uploads/', '/assets/uploads/missing.jpg', true];
        yield 'default prefix is not hardcoded' => ['uploads', '/media/missing.jpg', false];
        yield 'similar prefix' => ['media', '/media-library/missing.jpg', false];
        yield 'nested similar prefix' => ['assets/uploads', '/assets/uploads-old/missing.jpg', false];
        yield 'page' => ['media', '/missing-page', false];
    }

    #[DataProvider('mediaPaths')]
    public function testOnlyTheConfiguredMediaDirectoryGetsAnEmpty404(string $directory, string $path, bool $handled): void
    {
        $event = $this->event(Request::create($path), new NotFoundHttpException());

        new MediaNotFoundListener($directory)($event);

        self::assertSame($handled, $event->hasResponse());
        if (! $handled) {
            return;
        }

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testDeploymentInASubdirectory(): void
    {
        $request = Request::create('/cms/media/missing.jpg', server: [
            'SCRIPT_NAME' => '/cms/index.php',
            'SCRIPT_FILENAME' => '/var/www/public/cms/index.php',
        ]);
        $event = $this->event($request, new NotFoundHttpException());

        new MediaNotFoundListener('media')($event);

        self::assertTrue($event->hasResponse());
    }

    /** @return iterable<string, array{Throwable}> */
    public static function otherExceptions(): iterable
    {
        yield 'server error' => [new RuntimeException('Storage failed')];
        yield 'access denied' => [new AccessDeniedHttpException()];
        yield 'bad request wrapping a 404' => [new BadRequestHttpException(previous: new NotFoundHttpException())];
    }

    #[DataProvider('otherExceptions')]
    public function testOtherErrorsAreLeftAlone(Throwable $exception): void
    {
        $event = $this->event(Request::create('/media/missing.jpg'), $exception);

        new MediaNotFoundListener('media')($event);

        self::assertFalse($event->hasResponse());
    }

    public function testSubrequestsAreLeftAlone(): void
    {
        $event = $this->event(Request::create('/media/missing.jpg'), new NotFoundHttpException(), HttpKernelInterface::SUB_REQUEST);

        new MediaNotFoundListener('media')($event);

        self::assertFalse($event->hasResponse());
    }

    /** @return iterable<string, array{string, string}> */
    public static function missingMediaRequests(): iterable
    {
        yield 'original' => [Request::METHOD_GET, '/media/pushword-report-missing.jpg'];
        yield 'variant' => [Request::METHOD_GET, '/media/md/pushword-report-missing.webp'];
        yield 'unknown filter' => [Request::METHOD_GET, '/media/unknown-filter/piedweb-logo.webp'];
        yield 'malformed name' => [Request::METHOD_GET, '/media/md/broken%20image.webp'];
        yield 'encoded directory' => [Request::METHOD_GET, '/med%69a/pushword-report-missing.jpg'];
        yield 'malformed path' => [Request::METHOD_GET, '/media/md/photo.webp/extra/path'];
        yield 'directory' => [Request::METHOD_GET, '/media/'];
        yield 'head' => [Request::METHOD_HEAD, '/media/pushword-report-missing.jpg'];
    }

    #[Group('integration')]
    #[DataProvider('missingMediaRequests')]
    public function testMedia404BypassesTheErrorRenderer(string $method, string $path): void
    {
        $client = self::createClient();
        $client->request($method, $path);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        self::assertSame('', $client->getResponse()->getContent());
    }

    #[Group('integration')]
    public function testPage404StillRendersAnErrorPage(): void
    {
        $client = self::createClient(['debug' => false]);
        $client->request(Request::METHOD_GET, '/pushword-report-missing-page');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertStringContainsString('<html', (string) $client->getResponse()->getContent());
    }

    private function event(Request $request, Throwable $exception, int $type = HttpKernelInterface::MAIN_REQUEST): ExceptionEvent
    {
        return new ExceptionEvent(self::createStub(HttpKernelInterface::class), $request, $type, $exception);
    }
}
