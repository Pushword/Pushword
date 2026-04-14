<?php

namespace Pushword\Core\Tests\Scheduler;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Scheduler\CronScheduleProvider;

class CronScheduleProviderTest extends TestCase
{
    public function testEmptyCommandsReturnsEmptySchedule(): void
    {
        $provider = new CronScheduleProvider([]);
        $messages = iterator_to_array($provider->getSchedule()->getRecurringMessages());

        self::assertCount(0, $messages);
    }

    public function testCronEntryIsRegistered(): void
    {
        $provider = new CronScheduleProvider([
            ['command' => 'pw:static', 'on' => 'cron: 0 4 * * *'],
        ]);

        $messages = iterator_to_array($provider->getSchedule()->getRecurringMessages());

        self::assertCount(1, $messages);
    }

    public function testPublishEntryRegistersPwCronCheck(): void
    {
        $provider = new CronScheduleProvider([
            ['command' => 'pw:static -i', 'on' => 'publish'],
        ]);

        $messages = iterator_to_array($provider->getSchedule()->getRecurringMessages());

        self::assertCount(1, $messages);
    }

    public function testMultiplePublishEntriesRegisterOnlyOnePwCronCheck(): void
    {
        $provider = new CronScheduleProvider([
            ['command' => 'pw:static -i', 'on' => 'publish'],
            ['command' => 'pw:sitemap', 'on' => 'publish'],
        ]);

        $messages = iterator_to_array($provider->getSchedule()->getRecurringMessages());

        // Only one pw:cron check, not two
        self::assertCount(1, $messages);
    }

    public function testMixedEntriesAreBothRegistered(): void
    {
        $provider = new CronScheduleProvider([
            ['command' => 'pw:static -i', 'on' => 'publish'],
            ['command' => 'pw:static', 'on' => 'cron: 0 4 * * *'],
        ]);

        $messages = iterator_to_array($provider->getSchedule()->getRecurringMessages());

        self::assertCount(2, $messages);
    }
}
