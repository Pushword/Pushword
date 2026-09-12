<?php

declare(strict_types=1);

namespace Pushword\Core\BackgroundTask;

final readonly class RunCommandMessage
{
    /**
     * @param string[] $commandParts
     */
    public function __construct(
        public string $processType,
        public array $commandParts,
        public string $commandPattern,
    ) {
    }
}
