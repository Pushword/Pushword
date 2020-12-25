<?php

namespace Pushword\Core\Utils;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;

use function Safe\file_put_contents;
use function Safe\filemtime;
use function Safe\touch;

/**
 * Usage
 * (new LastTime($rootDir.'/../var/lastNoficationUpdatePageSendAt'))->wasRunSince(new DateInterval('P2H')).
 */
class LastTime
{
    public function __construct(protected string $filePath)
    {
    }

    public function wasRunSince(DateInterval $dateInterval): bool
    {
        $dateTime = $this->get();

        return null !== $dateTime && $dateTime->add($dateInterval) >= new DateTime('now');
    }

    /**
     * Return false if never runned else last datetime it was runned.
     * If $default is set, return $default time if never runned.
     *
     * @return DateTime|DateTimeImmutable|null
     */
    public function get(?string $default = null): ?DateTimeInterface
    {
        if (! file_exists($this->filePath)) {
            return null === $default ? null : new DateTime($default);
        }

        return new DateTime('@'.filemtime($this->filePath));
    }

    /**
     * @psalm-suppress InvalidNullableReturnType
     * @psalm-suppress NullableReturnStatement
     */
    public function safeGet(string $default): DateTimeInterface
    {
        return $this->get($default); // @phpstan-ignore-line
    }

    public function setWasRun(string $datetime = 'now', bool $setIfNotExist = true): void
    {
        if (! file_exists($this->filePath)) {
            if (! $setIfNotExist) {
                return;
            }

            file_put_contents($this->filePath, '');
        }

        touch($this->filePath, (new DateTime($datetime))->getTimestamp());
    }

    /**
     * alias for set was run.
     */
    public function set(string $datetime = 'now'): void
    {
        $this->setWasRun($datetime);
    }
}
