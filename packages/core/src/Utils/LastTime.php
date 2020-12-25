<?php

namespace Pushword\Core\Utils;

use DateInterval;
use DateTime;

/**
 * Usage
 * (new LastTime($rootDir.'/../var/lastNoficationUpdatePageSendAt'))->wasRunSince(new DateInterval('P2H')).
 */
class LastTime
{
    protected $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function wasRunSince(DateInterval $interval): bool
    {
        $previous = $this->get();

        if (false === $previous || $previous->add($interval) < new DateTime('now')) {
            return false;
        }

        return true;
    }

    /**
     * Return false if never runned else last datetime it was runned.
     * If $default is set, return $default time if never runned.
     */
    public function get($default = false)
    {
        if (! file_exists($this->filePath)) {
            return false === $default ? false : new DateTime($default);
        }

        return new DateTime('@'.filemtime($this->filePath));
    }

    public function setWasRun($datetime = 'now', $setIfNotExist = true): void
    {
        if (! file_exists($this->filePath)) {
            if (false === $setIfNotExist) {
                return;
            }
            file_put_contents($this->filePath, '');
        }

        touch($this->filePath, (new DateTime($datetime))->getTimestamp());
    }

    /**
     * alias for set was run.
     */
    public function set($datetime = 'now')
    {
        $this->setWasRun($datetime);
    }
}
