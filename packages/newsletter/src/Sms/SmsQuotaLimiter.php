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

    public function reserve(SmsSend $send, DateTimeImmutable $now): bool
    {
        $db = $this->em->getConnection();
        if (false === $db->fetchOne('SELECT id FROM newsletter_sms_quota_lock WHERE id = 1')) {
            try {
                $db->insert('newsletter_sms_quota_lock', ['id' => 1]);
            } catch (UniqueConstraintViolationException) {
                // Another sender initialized the shared lock.
            }
        }

        return $db->transactional(function () use ($db, $send, $now): bool {
            $db->executeStatement('UPDATE newsletter_sms_quota_lock SET id = id WHERE id = 1');
            $this->em->refresh($send);
            if (SmsState::Pending !== $send->state || ! $this->settings->canSend()) {
                return false;
            }

            /** @var list<array{id: int|string, phone: string, quota_reserved_at: string}> $recent */
            $recent = $db->fetchAllAssociative(
                'SELECT id, phone, quota_reserved_at FROM newsletter_sms_send WHERE quota_reserved_at > ? ORDER BY quota_reserved_at ASC, id ASC',
                [$now->modify('-24 hours')],
                [Types::DATETIME_IMMUTABLE],
            );
            $next = $now;
            $reserved = false;
            foreach ($recent as $reservation) {
                if ((int) $reservation['id'] === $send->id) {
                    $reserved = true;
                }
            }

            if (! $reserved) {
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
            }

            $next = $this->nextOpening($next);
            if ($next > $now) {
                $send->nextAttemptAt = $next;
                $this->em->flush();

                return false;
            }

            $send->quotaReservedAt = $now;
            $send->nextAttemptAt = null;

            $this->em->flush();

            return true;
        });
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
