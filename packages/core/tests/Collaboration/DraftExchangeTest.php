<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Collaboration;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Collaboration\DraftExchange;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class DraftExchangeTest extends TestCase
{
    public function testSharesDurableUpdatesWithTheRightDocumentAndRevision(): void
    {
        $directory = sys_get_temp_dir().'/pw-draft-'.bin2hex(random_bytes(8));
        $filesystem = new Filesystem();
        $exchange = new DraftExchange($directory, $filesystem, new LockFactory(new InMemoryStore()));
        $sync = static function (string $document, string $revision, ?string $expected, int $after, ?string $update = null) use ($exchange): array {
            $request = Request::create('/draft', Request::METHOD_POST, server: null === $expected ? [] : ['HTTP_IF_MATCH' => $expected], content: json_encode(['after' => $after, 'update' => $update], \JSON_THROW_ON_ERROR));
            $response = $exchange->sync($request, $document, $revision, 'Original');
            $content = $response->getContent();
            self::assertIsString($content);

            return [$response->getStatusCode(), json_decode($content, true, flags: \JSON_THROW_ON_ERROR)];
        };

        try {
            self::assertSame([200, ['revision' => '1', 'baseline' => 'Original', 'after' => 0, 'more' => false, 'updates' => []]], $sync('page:1', '1', null, 0));
            $first = base64_encode('browser update');
            $second = base64_encode('agent update');
            self::assertSame([409, ['error' => 'revision_mismatch', 'revision' => '1']], $sync('page:1', '1', null, 0, $first));
            self::assertSame([200, ['revision' => '1', 'baseline' => 'Original', 'after' => 1, 'more' => false, 'updates' => [$first]]], $sync('page:1', '1', '1', 0, $first));
            self::assertSame([200, ['revision' => '1', 'baseline' => null, 'after' => 1, 'more' => false, 'updates' => []]], $sync('page:1', '1', '1', 1, $first));
            self::assertSame([200, ['revision' => '1', 'baseline' => null, 'after' => 2, 'more' => false, 'updates' => [$second]]], $sync('page:1', '1', '1', 1, $second));
            self::assertSame([409, ['error' => 'draft_missing']], $sync('page:1', '1', '1', 3));
            self::assertSame([400, ['error' => 'invalid_update']], $sync('page:1', '1', '1', -1));
            self::assertSame([400, ['error' => 'invalid_update']], $sync('page:1', '1', '1', 0, '!!!'));
            self::assertSame([400, ['error' => 'invalid_update']], $sync('page:1', '1', '1', 0, ''));
            self::assertSame([409, ['error' => 'revision_mismatch', 'revision' => '2']], $sync('page:1', '2', '1', 2, $second));
            self::assertSame([200, ['revision' => '2', 'baseline' => 'Original', 'after' => 0, 'more' => false, 'updates' => []]], $sync('page:1', '2', '2', 0));
            self::assertSame([200, ['revision' => '1', 'baseline' => 'Original', 'after' => 0, 'more' => false, 'updates' => []]], $sync('repurpose:1', '1', '1', 0));
        } finally {
            $filesystem->remove($directory);
        }
    }

    public function testPagesTheUpdatesAndStopsAtTheRoomStorageLimit(): void
    {
        $directory = sys_get_temp_dir().'/pw-draft-'.bin2hex(random_bytes(8));
        $filesystem = new Filesystem();
        $exchange = new DraftExchange($directory, $filesystem, new LockFactory(new InMemoryStore()));
        $key = 'draft-'.hash('sha256', 'page:1:1');
        $file = $directory.'/collaboration/'.$key.'.json';
        $request = Request::create('/draft', Request::METHOD_POST, server: ['HTTP_IF_MATCH' => '1'], content: '{"after":0}');

        try {
            $updates = array_map(static fn (int $number): string => base64_encode((string) $number), range(1, 65));
            $filesystem->dumpFile($file, json_encode($updates, \JSON_THROW_ON_ERROR));
            $response = $exchange->sync($request, 'page:1', '1', 'Original');
            self::assertSame(['revision' => '1', 'baseline' => 'Original', 'after' => 64, 'more' => true, 'updates' => \array_slice($updates, 0, 64)], json_decode((string) $response->getContent(), true));

            $large = str_repeat('A', 16_777_216 - 4);
            $payload = json_encode([$large], \JSON_THROW_ON_ERROR);
            $filesystem->dumpFile($file, $payload);
            $request = Request::create('/draft', Request::METHOD_POST, server: ['HTTP_IF_MATCH' => '1'], content: '{"after":1,"update":"YWJj"}');
            self::assertSame(507, $exchange->sync($request, 'page:1', '1', 'Original')->getStatusCode());
            self::assertSame($payload, $filesystem->readFile($file));
        } finally {
            $filesystem->remove($directory);
        }
    }

    public function testHoldsTheRoomLockDuringTheWrite(): void
    {
        $store = new InMemoryStore();
        $locks = new LockFactory($store);
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects(self::once())->method('dumpFile')->willReturnCallback(static function (string $path) use ($locks): void {
            $writer = $locks->createLock(basename($path, '.json'));
            self::assertFalse($writer->acquire());
        });
        $exchange = new DraftExchange(sys_get_temp_dir(), $filesystem, $locks);
        $document = 'page:'.bin2hex(random_bytes(8));
        $request = Request::create('/draft', Request::METHOD_POST, server: ['HTTP_IF_MATCH' => '1'], content: '{"after":0,"update":"YWJj"}');

        self::assertSame(200, $exchange->sync($request, $document, '1', 'Original')->getStatusCode());
        self::assertTrue($locks->createLock('draft-'.hash('sha256', $document.':1'))->acquire());
    }

    public function testRejectsOversizedRequests(): void
    {
        $exchange = new DraftExchange(sys_get_temp_dir(), new Filesystem(), new LockFactory(new InMemoryStore()));
        $request = Request::create('/draft', Request::METHOD_POST, content: str_repeat('x', 262_145));
        $response = $exchange->sync($request, 'page:1', '1', 'Original');
        self::assertSame(413, $response->getStatusCode());
        self::assertSame('{"error":"update_too_large"}', $response->getContent());

        $request = Request::create('/draft', Request::METHOD_POST, content: str_pad('{"after":0}', 262_144));
        self::assertSame(200, $exchange->sync($request, 'page:1', '1', 'Original')->getStatusCode());
    }
}
