<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Collaboration;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Collaboration\DraftExchange;
use Pushword\Core\Collaboration\LiveProjection;
use Pushword\Core\Collaboration\LiveWrite;
use Pushword\Core\Entity\LiveEditingState;
use Pushword\Core\Entity\Page;
use Pushword\Version\Versionner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

#[Group('integration')]
final class DraftExchangeTest extends KernelTestCase
{
    public function testCommitsTheWinningProjectionAndKeepsTheRoomThroughLiveSaves(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $clock = new MockClock('2026-01-01');
        $write = self::getContainer()->get(LiveWrite::class);
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir().'/pw-live-'.bin2hex(random_bytes(8));
        $exchange = new DraftExchange($em, new LockFactory(new InMemoryStore()), $clock, $write, $filesystem, $directory, new LiveProjection());

        /** @var array{page: array<string, string>} $fixture */
        $fixture = json_decode($filesystem->readFile(__DIR__.'/fixtures/live-editing.json'), true, flags: \JSON_THROW_ON_ERROR);
        $snapshots = $fixture['page'];
        $page = new Page();
        $page->slug = 'live-editing-'.bin2hex(random_bytes(5));
        $page->h1 = 'Collaborative page';
        $page->host = 'localhost.dev';
        $page->mainContent = 'Initial text';
        $page->publishedAt = new DateTime('2024-01-01');

        $em->persist($page);
        $em->flush();

        $id = $page->id;
        self::assertNotNull($id);

        /** @return array{int, array<array-key, mixed>} */
        $send = static function (array $data, ?string $revision = null) use ($exchange, $page): array {
            $request = Request::create('/draft', Request::METHOD_POST, server: null === $revision ? [] : ['HTTP_IF_MATCH' => $revision], content: json_encode($data, \JSON_THROW_ON_ERROR));
            $response = $exchange->page($request, $page);

            $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
            self::assertIsArray($data);

            return [$response->getStatusCode(), $data];
        };

        try {
            $versionner = self::getContainer()->get(Versionner::class);
            $original = $versionner->getVersions('page', $id);
            self::assertCount(1, $original);
            self::assertSame([400, ['error' => 'invalid_update']], $send(['after' => -1]));
            self::assertSame([400, ['error' => 'invalid_update']], $send(['after' => 0, 'snapshot' => 'YWJj', 'content' => 'Bad']));
            self::assertSame([409, ['error' => 'revision_mismatch', 'revision' => '1']], $send(['after' => 0], '0'));

            $legacyUpdate = $snapshots['Initial text'];
            $oldRoom = hash('sha256', 'page:'.$id.':1');
            $filesystem->dumpFile($directory.'/collaboration/draft-'.$oldRoom.'.json', json_encode([$legacyUpdate], \JSON_THROW_ON_ERROR));
            [$status, $start] = $send(['after' => 0], '1');
            self::assertSame(200, $status);
            self::assertSame('Initial text', $start['baseline']);
            self::assertNull($start['snapshot']);
            self::assertSame([$legacyUpdate], $start['legacyUpdates']);
            $room = $start['room'];
            self::assertIsString($room);

            $edit = static fn (int $after, string $content, ?string $snapshot = null): array => [
                'room' => $room, 'after' => $after, 'snapshot' => $snapshot ?? $snapshots[$content], 'content' => $content,
            ];

            self::assertSame([422, ['error' => 'invalid_snapshot']], $send($edit(0, 'Broken state', 'YWJj')));
            self::assertSame([422, ['error' => 'invalid_snapshot']], $send($edit(0, 'Unrelated projection', $snapshots['First live edit'])));
            $em->refresh($page);
            self::assertSame('Initial text', $page->mainContent);

            [$status, $saved] = $send($edit(0, 'First live edit'));
            self::assertSame(200, $status);
            self::assertTrue($saved['accepted']);
            self::assertSame('2', $saved['revision']);
            self::assertSame([
                'room' => $room, 'revision' => '2', 'baseline' => null, 'after' => 1, 'snapshot' => null, 'accepted' => false, 'anchor' => null, 'legacyUpdates' => [],
            ], $send(['room' => $room, 'after' => 1])[1]);
            $em->refresh($page);
            self::assertSame('First live edit', $page->mainContent);
            self::assertTrue($page->isPublished());

            [$status, $behind] = $send($edit(0, 'Unmerged write', $snapshots['First live edit']));
            self::assertSame(200, $status);
            self::assertFalse($behind['accepted']);
            self::assertSame(1, $behind['after']);
            self::assertSame($snapshots['First live edit'], $behind['snapshot']);
            $em->refresh($page);
            self::assertSame('First live edit', $page->mainContent);

            [$status, $merged] = $send($edit(1, 'First live edit merged'));
            self::assertSame(200, $status);
            self::assertTrue($merged['accepted']);
            $em->refresh($page);
            self::assertSame('First live edit merged', $page->mainContent);
            self::assertSame($original, $versionner->getVersions('page', $id));
            [$status, $lateJoin] = $send(['after' => 0], '1');
            self::assertSame(200, $status);
            self::assertSame($room, $lateJoin['room']);
            self::assertSame(['baseline' => 'Initial text', 'snapshot' => null], $lateJoin['anchor']);
            self::assertSame($snapshots['First live edit merged'], $lateJoin['snapshot']);
            [$status, $lateJoin] = $send(['after' => 0], '2');
            self::assertSame(200, $status);
            self::assertSame(['baseline' => null, 'snapshot' => $snapshots['First live edit']], $lateJoin['anchor']);
            self::assertSame($snapshots['First live edit merged'], $lateJoin['snapshot']);

            $clock->sleep(300);
            [$status, $later] = $send($edit(2, 'New editing period'));
            self::assertSame(200, $status);
            self::assertTrue($later['accepted']);
            self::assertCount(2, $versionner->getVersions('page', $id));
            $checkpoint = $versionner->getLatestVersion('page', $id);
            self::assertNotNull($checkpoint);
            $previous = $versionner->populate(new Page(), 'page', $id, $checkpoint);
            self::assertInstanceOf(Page::class, $previous);
            self::assertSame('First live edit merged', $previous->mainContent);

            // The existing publication control is an ordinary write. An old
            // room cannot overwrite it, and the new room keeps the page held.
            $page->setHoldPublication(true);
            $em->flush();
            self::assertSame(409, $send($edit(3, 'Late edit', $snapshots['New editing period']))[0]);
            [$status, $reopened] = $send(['after' => 0]);
            self::assertSame(200, $status);
            self::assertNotSame($room, $reopened['room']);
            self::assertSame('New editing period', $reopened['baseline']);
            self::assertTrue($page->isHoldPublication());
            [$status, $held] = $send([
                'room' => $reopened['room'], 'after' => 0, 'snapshot' => $snapshots['Edit while held'], 'content' => 'Edit while held',
            ]);
            self::assertSame(200, $status);
            self::assertTrue($held['accepted']);
            $em->refresh($page);
            self::assertTrue($page->isHoldPublication());
            self::assertSame('Edit while held', $page->mainContent);

            $heldHistory = $versionner->getVersions('page', $id);
            for ($cursor = 1; $cursor <= 6; ++$cursor) {
                $clock->sleep(299);
                [$status, $burst] = $send([
                    'room' => $reopened['room'], 'after' => $cursor, 'snapshot' => $snapshots['Held edit '.$cursor], 'content' => 'Held edit '.$cursor,
                ]);
                self::assertSame(200, $status);
                self::assertTrue($burst['accepted']);
            }

            self::assertSame($heldHistory, $versionner->getVersions('page', $id));

            $clock->sleep(6);
            [$status, $afterCap] = $send([
                'room' => $reopened['room'], 'after' => 7, 'snapshot' => $snapshots['After thirty minutes'], 'content' => 'After thirty minutes',
            ]);
            self::assertSame(200, $status);
            self::assertTrue($afterCap['accepted']);
            self::assertCount(\count($heldHistory) + 1, $versionner->getVersions('page', $id));
        } finally {
            $filesystem->remove($directory);
            $state = $em->find(LiveEditingState::class, 'page:'.$id);
            if ($state instanceof LiveEditingState) {
                $em->remove($state);
            }

            $em->remove($page);
            $em->flush();
        }
    }

    public function testRejectsOversizedRequests(): void
    {
        self::bootKernel();
        $exchange = self::getContainer()->get(DraftExchange::class);
        $request = Request::create('/draft', Request::METHOD_POST, content: str_repeat('x', 4_194_305));
        self::assertSame(413, $exchange->page($request, new Page())->getStatusCode());
    }

    public function testServerPatchPreservesTheLegacyJournalAndRollsBackRejectedEdits(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir().'/pw-live-'.bin2hex(random_bytes(8));
        $locks = new LockFactory(new InMemoryStore());
        $exchange = new DraftExchange($em, $locks, new MockClock(), self::getContainer()->get(LiveWrite::class), $filesystem, $directory, new LiveProjection());
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->slug = 'server-patch-'.bin2hex(random_bytes(5));
        $page->h1 = 'Server patch';
        $page->mainContent = 'Initial text';

        $em->persist($page);
        $em->flush();

        $document = 'page:'.$page->id;
        $probe = $locks->createLock('live-editing-'.$document);

        try {
            $key = hash('sha256', $document.':'.$page->writeVersion);
            // A Yjs update appending a suffix to the deterministic baseline.
            $legacy = 'AgECAIQBCxIg4oCUIGxlZ2FjeSBzdWZmaXgBAQAEAQRib2R5DEluaXRpYWwgdGV4dAA=';
            $filesystem->dumpFile($directory.'/collaboration/draft-'.$key.'.json', json_encode([$legacy], \JSON_THROW_ON_ERROR));
            $rejected = new JsonResponse(['error' => 'invalid_content'], Response::HTTP_UNPROCESSABLE_ENTITY);
            $prepare = static function () use ($probe): array {
                self::assertFalse($probe->acquire());

                return ['content' => 'Fresh text', 'replacements' => [['find' => 'Initial', 'replace' => 'Fresh']]];
            };
            $reject = static function (string $body) use ($page, $rejected): JsonResponse {
                $page->mainContent = $body;

                return $rejected;
            };
            self::assertSame($rejected, $exchange->patchPage($page, $prepare, $reject));
            self::assertSame('Initial text', $page->mainContent);

            $join = Request::create('/draft', Request::METHOD_POST, content: '{"after":0}');
            self::assertSame(200, $exchange->page($join, $page)->getStatusCode());
            $state = $em->find(LiveEditingState::class, $document);
            self::assertInstanceOf(LiveEditingState::class, $state);
            $room = $state->room;

            $invalid = static fn (): array => ['content' => 'Mismatched projection', 'replacements' => $prepare()['replacements']];
            $never = static fn (): never => self::fail('An invalid projection must not be applied.');
            self::assertSame(422, $exchange->patchPage($page, $invalid, $never)?->getStatusCode());
            self::assertSame($rejected, $exchange->patchPage($page, $prepare, $reject));
            self::assertSame('Initial text', $page->mainContent);
            self::assertSame(0, $state->cursor);

            $apply = static function (string $body) use ($page): null {
                $page->mainContent = $body;

                return null;
            };
            self::assertNull($exchange->patchPage($page, $prepare, $apply));
            $em->refresh($page);
            $em->refresh($state);
            self::assertSame('Fresh text — legacy suffix', $page->mainContent);
            self::assertSame($room, $state->room);
            self::assertSame(1, $state->cursor);
            self::assertSame((string) $page->writeVersion, $state->revision);

            // A separate writer changes the row while this entity still has its previous revision.
            $em->getConnection()->executeStatement(
                'UPDATE page SET main_content = ?, write_version = write_version + 1 WHERE id = ?',
                ['Fresh text — external edit', $page->id],
            );
            $later = static function () use ($page): array {
                self::assertSame('Fresh text — external edit', $page->mainContent);

                return [
                    'content' => 'Later text — external edit',
                    'replacements' => [['find' => 'Fresh', 'replace' => 'Later']],
                ];
            };
            self::assertNull($exchange->patchPage($page, $later, $apply));
            self::assertSame('Later text — external edit', $page->mainContent);
            self::assertSame(1, $state->cursor);
            self::assertTrue($probe->acquire());
            $probe->release();
        } finally {
            $filesystem->remove($directory);
            $state = $em->find(LiveEditingState::class, $document);
            if ($state instanceof LiveEditingState) {
                $em->remove($state);
            }

            $em->remove($page);
            $em->flush();
        }
    }
}
