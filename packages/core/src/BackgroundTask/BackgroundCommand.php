<?php

namespace Pushword\Core\BackgroundTask;

final class BackgroundCommand
{
    /**
     * A console child inherits APP_ENV, and under PHPUnit that variable is not exported:
     * the task then ran in dev — against the dev database, writing its media derivatives
     * into the dev app's own directories. Pin the env the task was dispatched from.
     *
     * @param string[] $commandParts
     *
     * @return string[]
     */
    public static function pinEnvironment(array $commandParts, string $environment): array
    {
        if (! \in_array('bin/console', $commandParts, true)) {
            return $commandParts;
        }

        return [...$commandParts, '--env='.$environment];
    }
}
