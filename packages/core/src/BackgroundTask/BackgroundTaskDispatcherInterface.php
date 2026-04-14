<?php

namespace Pushword\Core\BackgroundTask;

interface BackgroundTaskDispatcherInterface
{
    /** @param string[] $commandParts */
    public function dispatch(string $processType, array $commandParts, string $commandPattern): void;
}
