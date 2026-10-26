<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\EventListener;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Pushword\Core\EventListener\SessionSaveListener;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class SessionSaveListenerTest extends TestCase
{
    public function testRequestWithoutSessionIsLeftAlone(): void
    {
        $request = Request::create('/admin');

        new SessionSaveListener(new TokenStorage())->release($this->event($request));

        self::assertFalse($request->hasSession());
    }

    public function testSessionFactoryRemainsLazy(): void
    {
        $request = Request::create('/admin');
        $request->setSessionFactory(static fn () => self::fail('An unused session must not be initialized.'));

        new SessionSaveListener(new TokenStorage())->release($this->event($request));

        self::assertFalse($request->hasSession(true));
    }

    public function testUnstartedSessionIsLeftAlone(): void
    {
        $request = Request::create('/admin');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        new SessionSaveListener(new TokenStorage())->release($this->event($request));

        self::assertFalse($session->isStarted());
        self::assertSame('', $session->getId());
    }

    #[DataProvider('adminPaths')]
    public function testAdminRequestReleasesItsStartedSession(string $path): void
    {
        $request = Request::create($path);
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $session->set('value', 'preserved');

        new SessionSaveListener(new TokenStorage())->release($this->event($request));

        self::assertFalse($session->isStarted());
        self::assertSame('preserved', $session->get('value'));
    }

    /** @return iterable<string, array{string}> */
    public static function adminPaths(): iterable
    {
        yield 'dashboard' => ['/admin'];
        yield 'trailing slash' => ['/admin/'];
        yield 'nested action' => ['/admin/page/1/edit'];
    }

    #[DataProvider('excludedRequests')]
    public function testExcludedRequestKeepsItsSessionOpen(string $path, int $requestType, bool $stateless): void
    {
        $request = Request::create($path);
        $request->attributes->set('_stateless', $stateless);

        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $session->set('value', 'preserved');

        new SessionSaveListener(new TokenStorage())->release($this->event($request, $requestType));

        self::assertTrue($session->isStarted());
        self::assertSame('preserved', $session->get('value'));
    }

    /**
     * Outside the admin a controller may log a user in or out, and Symfony cannot
     * migrate or invalidate a session that was saved before it.
     *
     * @return iterable<string, array{string, int, bool}>
     */
    public static function excludedRequests(): iterable
    {
        yield 'subrequest' => ['/admin', HttpKernelInterface::SUB_REQUEST, false];
        yield 'stateless' => ['/admin', HttpKernelInterface::MAIN_REQUEST, true];
        yield 'login' => ['/login', HttpKernelInterface::MAIN_REQUEST, false];
        yield 'site page' => ['/administration', HttpKernelInterface::MAIN_REQUEST, false];
    }

    /**
     * A sign-out in another tab empties the stored session while this request runs;
     * the firewall must not write this request's token back into it.
     */
    public function testASignOutDuringTheRequestIsNotUndone(): void
    {
        $storage = new MockArraySessionStorage();
        [$listener, $request, $tokenStorage] = $this->releasedSignedInRequest($storage);

        $storage->setSessionData([]);
        $listener->keepSignOut($this->responseEvent($request));

        self::assertNull($tokenStorage->getToken());
    }

    public function testAStillSignedInSessionKeepsItsToken(): void
    {
        [$listener, $request, $tokenStorage] = $this->releasedSignedInRequest(new MockArraySessionStorage());

        $listener->keepSignOut($this->responseEvent($request));

        self::assertInstanceOf(TokenInterface::class, $tokenStorage->getToken());
    }

    public function testSignOutIsIgnoredOutsideTheMainRequest(): void
    {
        $storage = new MockArraySessionStorage();
        [$listener, $request, $tokenStorage] = $this->releasedSignedInRequest($storage);

        $storage->setSessionData([]);
        $listener->keepSignOut($this->responseEvent($request, HttpKernelInterface::SUB_REQUEST));

        self::assertInstanceOf(TokenInterface::class, $tokenStorage->getToken());
    }

    public function testASessionNeverSignedInLeavesTheTokenAlone(): void
    {
        $request = Request::create('/admin/page');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $session->set('value', 'preserved');

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(self::createStub(TokenInterface::class));

        $listener = new SessionSaveListener($tokenStorage);
        $listener->release($this->event($request));
        $listener->keepSignOut($this->responseEvent($request));

        self::assertInstanceOf(TokenInterface::class, $tokenStorage->getToken());
    }

    public function testARequestOutsideTheAdminKeepsItsTokenWhateverTheSession(): void
    {
        $storage = new MockArraySessionStorage();
        $request = Request::create('/login');
        $session = new Session($storage);
        $request->setSession($session);
        $session->set('_security_main', 'serialized token');

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(self::createStub(TokenInterface::class));

        $listener = new SessionSaveListener($tokenStorage);
        $listener->release($this->event($request));

        $storage->setSessionData([]);
        $listener->keepSignOut($this->responseEvent($request));

        self::assertInstanceOf(TokenInterface::class, $tokenStorage->getToken());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAnotherProcessCanUseTheNativeSessionBeforeTheControllerFinishes(): void
    {
        $directory = sys_get_temp_dir().'/pushword-session-'.bin2hex(random_bytes(8));
        $session = new Session(new NativeSessionStorage(
            ['use_cookies' => false, 'gc_probability' => 0],
            new NativeFileSessionHandler($directory),
        ));

        try {
            $request = Request::create('/admin/media/block', Request::METHOD_POST);
            $request->setSession($session);
            $session->set('before', 'preserved');
            $path = $directory.'/sess_'.$session->getId();
            self::assertSame('locked', $this->probeLock($path));

            new SessionSaveListener(new TokenStorage())->release($this->event($request));

            self::assertSame('available', $this->probeLock($path));
            $writer = new Process([\PHP_BINARY, '-r', <<<'PHP'
                session_save_path($argv[1]);
                session_id($argv[2]);
                session_start(['use_cookies' => false, 'cache_limiter' => '']);
                $_SESSION['_sf2_attributes']['concurrent'] = 'saved';
                session_write_close();
                PHP, $directory, $session->getId()], timeout: 5);
            $writer->mustRun();
            self::assertSame('', $writer->getErrorOutput());

            // A controller can still read or write after the early save, and it
            // reloads concurrent changes instead of overwriting a stale snapshot.
            self::assertSame('saved', $session->get('concurrent'));
            self::assertSame('preserved', $session->get('before'));
            $session->getFlashBag()->add('success', 'Uploaded');
            $session->save();
            self::assertSame(['Uploaded'], $session->getFlashBag()->get('success'));
        } finally {
            if ($session->isStarted()) {
                $session->save();
            }

            new Filesystem()->remove($directory);
        }
    }

    private function probeLock(string $path): string
    {
        $process = new Process([\PHP_BINARY, '-r', <<<'PHP'
            $file = fopen($argv[1], 'r+');
            echo flock($file, LOCK_EX | LOCK_NB) ? 'available' : 'locked';
            fclose($file);
            PHP, $path], timeout: 5);
        $process->mustRun();
        self::assertSame('', $process->getErrorOutput());

        return $process->getOutput();
    }

    /** @return array{SessionSaveListener, Request, TokenStorage} */
    private function releasedSignedInRequest(MockArraySessionStorage $storage): array
    {
        $request = Request::create('/admin/page');
        $session = new Session($storage);
        $request->setSession($session);
        $session->set('_security_main', 'serialized token');

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(self::createStub(TokenInterface::class));

        $listener = new SessionSaveListener($tokenStorage);
        $listener->release($this->event($request));
        self::assertFalse($session->isStarted());

        return [$listener, $request, $tokenStorage];
    }

    private function responseEvent(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        return new ResponseEvent(self::createStub(HttpKernelInterface::class), $request, $type, new Response());
    }

    private function event(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): ControllerArgumentsEvent
    {
        return new ControllerArgumentsEvent(self::createStub(HttpKernelInterface::class), static fn (): null => null, [], $request, $type);
    }
}
