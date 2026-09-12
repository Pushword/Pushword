<?php

declare(strict_types=1);

namespace Pushword\Core\BackgroundTask;

interface BackgroundTaskDispatcherInterface
{
    /** @param string[] $commandParts */
    public function dispatch(string $processType, array $commandParts, string $commandPattern): void;
}
