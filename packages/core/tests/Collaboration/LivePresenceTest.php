<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Collaboration;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Collaboration\LivePresence;
use Pushword\Core\Entity\User;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
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
        self::assertSame(['collaborative' => true, 'participants' => []], $presence->snapshot($document, $viewer));
        $presence->recordApi($document, $viewer, new JsonResponse([], Response::HTTP_CONFLICT));
        self::assertSame([], $presence->snapshot($document, $viewer)['participants']);

        $presence->sync($request, $document, $join, $editor);
        $clock->sleep(5);
        $request->headers->set('X-Collaboration-Tab', str_repeat('b', 32));
        $presence->sync($request, $document, $join, $editor);
        self::assertSame([['id' => 2, 'name' => 'Élodie', 'api' => false]], $presence->snapshot($document, $viewer)['participants']);
        $clock->sleep(3);
        $presence->recordApi($document, $viewer, $join());
        $presence->recordApi($document, $editor, $join());
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
}
