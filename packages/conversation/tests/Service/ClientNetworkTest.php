<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Conversation\Service\ClientNetwork;

final class ClientNetworkTest extends TestCase
{
    /** @return iterable<string, array{?string, string}> */
    public static function ipProvider(): iterable
    {
        yield 'ipv4, whole' => ['192.0.2.52', '192.0.2.52'];
        yield 'ipv6, its /64' => ['2001:db8:1:2:3:4:5:6', '2001:db8:1:2::'];
        yield 'another address of that /64' => ['2001:db8:1:2::ff', '2001:db8:1:2::'];
        yield 'ipv4-mapped ipv6, as its ipv4' => ['::ffff:192.0.2.52', '192.0.2.52'];
        yield 'no address' => [null, 'unknown'];
    }

    #[DataProvider('ipProvider')]
    public function testLimitersCountIpv4AddressesAndIpv6Networks(?string $ip, string $network): void
    {
        self::assertSame($network, ClientNetwork::of($ip));
    }
}
