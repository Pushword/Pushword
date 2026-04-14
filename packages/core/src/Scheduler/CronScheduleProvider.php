<?php

namespace Pushword\Core\Scheduler;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('pushword')]
final readonly class CronScheduleProvider implements ScheduleProviderInterface
{
    /** @param array<array{command: string, on: string}> $scheduledCommands */
    public function __construct(
        private array $scheduledCommands,
    ) {
    }

    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();
        $hasPublishTrigger = false;

        foreach ($this->scheduledCommands as $entry) {
            if (str_starts_with($entry['on'], 'cron:')) {
                $cronExpr = trim(substr($entry['on'], 5));
                $schedule->add(
                    RecurringMessage::cron($cronExpr, new RunCommandMessage($entry['command'])),
                );
            } elseif ('publish' === $entry['on'] && ! $hasPublishTrigger) {
                $schedule->add(
                    RecurringMessage::every(300, new RunCommandMessage('pw:cron')),
                );
                $hasPublishTrigger = true;
            }
        }

        return $schedule;
    }
}
