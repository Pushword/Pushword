<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Collaboration;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Collaboration\LivePresence;
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
}
