<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Sms;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Pushword\Newsletter\Entity\SmsSend;
use Pushword\Newsletter\Enum\SmsState;

final readonly class SmsQuotaLimiter
{
    public function __construct(private EntityManagerInterface $em, private SmsSettings $settings)
    {
    }

    public function reserve(SmsSend $send, DateTimeImmutable $now, ?DateTimeImmutable $testRetryAt = null): bool
    {
        $db = $this->em->getConnection();
        if (false === $db->fetchOne('SELECT id FROM newsletter_sms_quota_lock WHERE id = 1')) {
            try {
                $db->insert('newsletter_sms_quota_lock', ['id' => 1]);
            } catch (UniqueConstraintViolationException) {
                // Another sender initialized the shared lock.
            }
        }

        return $db->transactional(function () use ($db, $send, $now, $testRetryAt): bool {
            $db->executeStatement('UPDATE newsletter_sms_quota_lock SET id = id WHERE id = 1');
            $this->em->refresh($send);
            if (SmsState::Pending !== $send->state || ! $this->settings->canSend()
                || (! $testRetryAt instanceof DateTimeImmutable && $send->nextAttemptAt > $now)) {
                return false;
            }

            $next = $testRetryAt instanceof DateTimeImmutable ? $now : $this->nextAllowed($send, $now);
            if ($next > $now) {
                $send->nextAttemptAt = $next;
                $this->em->flush();

                return false;
            }

            $send->quotaReservedAt = $now;
            $send->nextAttemptAt = $testRetryAt;

            $this->em->flush();

            return true;
        });
    }

    private function nextAllowed(SmsSend $send, DateTimeImmutable $now): DateTimeImmutable
    {
        /** @var list<array{id: int|string, phone: string, quota_reserved_at: string}> $recent */
        $recent = $this->em->getConnection()->fetchAllAssociative(
            'SELECT id, phone, quota_reserved_at FROM newsletter_sms_send WHERE quota_reserved_at > ? AND (? OR NOT is_test) ORDER BY quota_reserved_at ASC, id ASC',
            [$now->modify('-24 hours'), $send->isTest],
            [Types::DATETIME_IMMUTABLE, Types::BOOLEAN],
        );
        foreach ($recent as $reservation) {
            if ((int) $reservation['id'] === $send->id) {
                return $this->nextOpening($now);
            }
        }

        $next = $now;
        if (\count($recent) >= $this->settings->dailyLimit) {
            $next = max($next, $this->date($recent[\count($recent) - $this->settings->dailyLimit]['quota_reserved_at'])->modify('+24 hours'));
        }

        foreach ($recent as $reservation) {
            $date = $this->date($reservation['quota_reserved_at']);
            $next = max($next, $date->modify('+'.$this->settings->intervalSeconds.' seconds'));
            if ($reservation['phone'] === $send->phone) {
                $next = max($next, $date->modify('+24 hours'));
            }
        }

        return $this->nextOpening($next);
    }

    private function nextOpening(DateTimeImmutable $candidate): DateTimeImmutable
    {
        $local = $candidate->setTimezone(new DateTimeZone($this->settings->timezone));
        for ($offset = 0; $offset < 3; ++$offset) {
            $day = $local->modify('+'.$offset.' days');
            $start = $day->setTime($this->settings->startHour, 0);
            $end = $day->setTime($this->settings->endHour, 0);
            if ($start < $end && $local < $end) {
                return max($local, $start)->setTimezone(new DateTimeZone('UTC'));
            }
        }

        throw new LogicException('Invalid SMS sending window.');
    }

    private function date(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date, new DateTimeZone('UTC'));
    }
}
