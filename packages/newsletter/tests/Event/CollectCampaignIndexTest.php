<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Event;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Newsletter\Event\CampaignIndexItem;
use Pushword\Newsletter\Event\CollectCampaignIndex;

final class CollectCampaignIndexTest extends TestCase
{
    public function testSourceIdentitiesStaySeparateAndActionsCanBeEnriched(): void
    {
        $index = new CollectCampaignIndex();
        $index->add(new CampaignIndexItem('pushword', '0', 'Local'));
        $index->add(new CampaignIndexItem('external', '0', 'Remote'));
        $index->items['pushword:0']->actions['Commercial results'] = '/results';
        $index->notices['external'] = 'Last synchronized yesterday.';

        self::assertSame(['pushword:0', 'external:0'], array_keys($index->items));
        self::assertSame(['/results'], array_values($index->items['pushword:0']->actions));
        self::assertNull($index->items['external:0']->status);
        self::assertNull($index->items['external:0']->scheduledAt);
        self::assertNull($index->items['external:0']->sentAt);
        self::assertSame('Last synchronized yesterday.', $index->notices['external']);
    }

    public function testDuplicateIdentityCannotReplaceAnExistingCampaign(): void
    {
        $index = new CollectCampaignIndex();
        $index->add(new CampaignIndexItem('pushword', '42', 'Original'));
        $this->expectException(LogicException::class);
        $index->add(new CampaignIndexItem('pushword', '42', 'Replacement'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidIdentities(): iterable
    {
        yield 'missing source' => ['', '42'];
        yield 'ambiguous source separator' => ['source:other', '42'];
        yield 'missing ID' => ['external', ''];
    }

    #[DataProvider('invalidIdentities')]
    public function testInvalidIdentitiesAreRejected(string $source, string $id): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CampaignIndexItem($source, $id, 'Campaign');
    }

    /** @return iterable<string, array{string, bool}> */
    public static function navigationUrls(): iterable
    {
        yield 'HTTPS' => ['https://example.org/campaign/42', true];
        yield 'HTTP' => ['http://example.org/campaign/42', true];
        yield 'root path' => ['/campaign/42?preview=1', true];
        yield 'encoded space' => ['/campaign/a%20b', true];
        yield 'javascript' => ['javascript:alert(1)', false];
        yield 'data' => ['data:text/html,hello', false];
        yield 'relative path' => ['campaign/42', false];
        yield 'protocol relative' => ['//example.org/campaign/42', false];
        yield 'backslash normalization' => ['/\\example.org', false];
        yield 'control normalization' => ["/\n/example.org", false];
        yield 'empty HTTP authority' => ['https://', false];
    }

    #[DataProvider('navigationUrls')]
    public function testNavigationUrls(string $url, bool $valid): void
    {
        if (! $valid) {
            $this->expectException(InvalidArgumentException::class);
        }

        $item = new CampaignIndexItem('external', '42', 'Campaign', actions: ['Open' => $url]);
        self::assertSame($url, $item->actions['Open']);
    }
}
