<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Collaboration;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Collaboration\LivePresence;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

final class LivePresenceTest extends TestCase
{
    public function testAnnouncesSuccessfulParticipantsAndExpiresEachTabSeparately(): void
    {
        $clock = new MockClock();
        $presence = new LivePresence(new ArrayAdapter(), $clock, new LockFactory(new InMemoryStore()));
        $first = str_repeat('a', 32);
        $second = str_repeat('b', 32);
        $join = static fn (int $status = Response::HTTP_OK): JsonResponse => new JsonResponse(['revision' => '1'], $status);
        $page = 'page:1';

        self::assertFalse($presence->isActive($page));
        $request = Request::create('/draft', Request::METHOD_POST, server: ['HTTP_X_COLLABORATION_TAB' => $first]);
        $failure = $join(Response::HTTP_CONFLICT);
        self::assertSame($failure, $presence->sync($request, $page, static fn (): JsonResponse => $failure));
        self::assertFalse($presence->isActive($page));

        foreach (['invalid', 'x'.$first, $first.'x'] as $invalid) {
            $request->headers->set('X-Collaboration-Tab', $invalid);
            $presence->sync($request, $page, $join);
            self::assertFalse($presence->isActive($page));
        }

        $request->headers->set('X-Collaboration-Tab', $first);
        $presence->sync($request, $page, $join);
        self::assertTrue($presence->isActive($page));
        self::assertFalse($presence->isActive('repurpose:1'));
        self::assertFalse($presence->isActive('page:2'));

        $clock->sleep(8);
        $request->headers->set('X-Collaboration-Tab', $second);
        $presence->sync($request, $page, $join);

        $request->setMethod(Request::METHOD_DELETE);
        $request->headers->set('X-Collaboration-Tab', $first);

        $never = static fn (): never => self::fail('Leaving must not call the content exchange.');
        self::assertSame(Response::HTTP_NO_CONTENT, $presence->sync($request, $page, $never)->getStatusCode());
        self::assertTrue($presence->isActive($page));

        $clock->sleep(11);
        self::assertTrue($presence->isActive($page));
        $clock->sleep(1);
        self::assertFalse($presence->isActive($page));

        $request->setMethod(Request::METHOD_POST);
        $request->headers->set('X-Collaboration-Tab', $second);

        $presence->sync($request, $page, $join);
        self::assertTrue($presence->isActive($page));
        $request->setMethod(Request::METHOD_DELETE);
        $presence->sync($request, $page, $never);
        self::assertFalse($presence->isActive($page));
        $request->headers->remove('X-Collaboration-Tab');
        self::assertSame(Response::HTTP_NO_CONTENT, $presence->sync($request, $page, $never)->getStatusCode());
    }

    public function testListsOtherAccountsAndDistinguishesApiPresenceForTheSameAccount(): void
    {
        $clock = new MockClock();
        $presence = new LivePresence(new ArrayAdapter(), $clock, new LockFactory(new InMemoryStore()));
        $viewer = new User();
        $viewer->email = 'robin@example.tld';
        $viewer->username = '  ';

        $editor = new User();
        $editor->username = 'Élodie';

        $id = new ReflectionProperty(User::class, 'id');
        $id->setValue($viewer, 1);
        $id->setValue($editor, 2);

        $document = 'page:1';
        $request = Request::create('/draft', Request::METHOD_POST, server: ['HTTP_X_COLLABORATION_TAB' => str_repeat('a', 32)]);
        $join = static fn (): JsonResponse => new JsonResponse(['revision' => '1']);

        $presence->sync($request, $document, $join);
        $presence->sync($request, $document, $join, $viewer);

        $failure = new JsonResponse([], Response::HTTP_CONFLICT);
        self::assertSame($failure, $presence->syncApi($document, $viewer, static fn (): JsonResponse => $failure));
        $afterFailure = $presence->snapshot($document, $viewer);
        self::assertSame(['collaborative' => true, 'participants' => []], $afterFailure);

        $presence->sync($request, $document, $join, $editor);
        $clock->sleep(5);
        $request->headers->set('X-Collaboration-Tab', str_repeat('b', 32));
        $presence->sync($request, $document, $join, $editor);
        self::assertSame([['id' => 2, 'name' => 'Élodie', 'api' => false]], $presence->snapshot($document, $viewer)['participants']);
        $clock->sleep(3);
        $presence->syncApi($document, $viewer, $join);
        $presence->syncApi($document, $editor, $join);
        self::assertSame(['collaborative' => false, 'participants' => []], $presence->snapshot('page:2', $viewer));

        $request->setMethod(Request::METHOD_DELETE);
        $request->headers->set('X-Collaboration-Tab', str_repeat('a', 32));

        $presence->sync($request, $document, $join, $viewer);
        $request->headers->set('X-Collaboration-Tab', str_repeat('b', 32));
        $presence->sync($request, $document, $join, $editor);
        self::assertSame([
            ['id' => 2, 'name' => 'Élodie', 'api' => false],
            ['id' => 1, 'name' => 'robin', 'api' => true],
            ['id' => 2, 'name' => 'Élodie', 'api' => true],
        ], $presence->snapshot($document, $viewer)['participants']);
        $clock->sleep(4);
        self::assertSame([
            ['id' => 1, 'name' => 'robin', 'api' => true],
            ['id' => 2, 'name' => 'Élodie', 'api' => true],
        ], $presence->snapshot($document, $viewer)['participants']);
        $clock->sleep(8);
        self::assertSame(['collaborative' => false, 'participants' => []], $presence->snapshot($document, $viewer));
    }

    public function testPresenceAndGuardedWritesHoldTheSameLock(): void
    {
        $clock = new MockClock();
        $locks = new LockFactory(new InMemoryStore());
        $presence = new LivePresence(new ArrayAdapter(), $clock, $locks);
        $page = new Page();
        new ReflectionProperty(Page::class, 'id')->setValue($page, 42);
        $document = 'page:42';
        $probe = $locks->createLock('live_presence.'.hash('sha256', $document));
        $lockedOperation = static function () use ($probe): JsonResponse {
            self::assertFalse($probe->acquire());

            return new JsonResponse([]);
        };
        $write = Request::create('/write', Request::METHOD_PUT, server: ['HTTP_X_PUSHWORD_RESPECT_COLLABORATION' => '1']);
        $join = Request::create('/draft', Request::METHOD_POST, server: ['HTTP_X_COLLABORATION_TAB' => str_repeat('a', 32)]);

        self::assertSame(Response::HTTP_OK, $presence->guardPageWrite($write, $page, $lockedOperation)->getStatusCode());
        self::assertSame(Response::HTTP_OK, $presence->sync($join, $document, $lockedOperation)->getStatusCode());
        self::assertTrue($probe->acquire(), 'The presence lock is released after a join.');
        $probe->release();

        $never = static fn (): never => self::fail('A guarded write must not run with an active session.');
        $blocked = $presence->guardPageWrite($write, $page, $never);
        self::assertSame(Response::HTTP_LOCKED, $blocked->getStatusCode());
        self::assertJsonStringEqualsJsonString('{"error":"collaboration_active","id":42}', (string) $blocked->getContent());

        $clock->sleep(12);
        $editor = new User();
        $editor->username = 'Editor';

        $presence->syncApi($document, $editor, $lockedOperation);
        self::assertSame(Response::HTTP_LOCKED, $presence->guardPageWrite($write, $page, $never)->getStatusCode());
        $write->headers->set(LivePresence::RESPECT_COLLABORATION, '0');
        self::assertSame(Response::HTTP_OK, $presence->guardPageWrite($write, $page, static fn (): JsonResponse => new JsonResponse([]))->getStatusCode());
    }

    public function testGuardWaitsForTheLockAndReleasesItEvenIfTheSaveThrows(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects(self::once())->method('acquire')->with(true)->willReturn(true);
        $lock->expects(self::once())->method('release');
        $locks = self::createStub(LockFactory::class);
        $locks->method('createLock')->willReturn($lock);
        $presence = new LivePresence(new ArrayAdapter(), new MockClock(), $locks);
        $write = Request::create('/write', Request::METHOD_PUT, server: ['HTTP_X_PUSHWORD_RESPECT_COLLABORATION' => '1']);

        $this->expectExceptionMessageIsOrContains('save failed');
        $presence->guardPageWrite($write, new Page(), static fn (): never => throw new RuntimeException('save failed'));
    }
}
