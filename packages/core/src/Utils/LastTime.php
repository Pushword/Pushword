<?php

namespace Pushword\Core\Utils;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Usage
 * (new LastTime($rootDir.'/../var/lastNoficationUpdatePageSendAt'))->wasRunSince(new DateInterval('P2H')).
 */
class LastTime
{
    private readonly Filesystem $filesystem;

    public function __construct(protected string $filePath)
    {
        $this->filesystem = new Filesystem();
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
        if (! $this->filesystem->exists($this->filePath)) {
            return null === $default ? null : new DateTime($default);
        }

        return new DateTime('@'.filemtime($this->filePath));
    }

    public function safeGet(string $default): DateTimeInterface
    {
        return $this->get($default); // @phpstan-ignore-line
    }

    public function setWasRun(string $datetime = 'now', bool $setIfNotExist = true): void
    {
        if (! $this->filesystem->exists($this->filePath)) {
            if (! $setIfNotExist) {
                return;
            }

            $this->filesystem->dumpFile($this->filePath, '');
        }

        $this->filesystem->touch($this->filePath, new DateTime($datetime)->getTimestamp());
    }

    /**
     * alias for set was run.
     */
    public function set(string $datetime = 'now'): void
    {
        $this->setWasRun($datetime);
    }
}
