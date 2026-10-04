<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Delivery;

use PHPUnit\Framework\TestCase;
use Pushword\Newsletter\Delivery\SendContext;

final class SendContextTest extends TestCase
{
    public function testASnapshotFromBeforeSystemBaseUrlsRemainsReadable(): void
    {
        $context = SendContext::fromArray([
            'mainHost' => 'brand.example.test',
            'fromEmail' => 'newsletter@example.test',
            'fromName' => 'Brand',
            'replyTo' => null,
            'postalAddress' => null,
            'footerMarkdown' => '',
            'audienceName' => 'Preferences',
        ]);

        self::assertNull($context->systemLinkBaseUrl);
        self::assertSame('brand.example.test', $context->mainHost);
    }
}
