<?php

namespace Pushword\StaticGenerator;

/**
 * Interface for generators that support incremental mode.
 */
interface IncrementalGeneratorInterface
{
    /**
     * Enable or disable incremental mode.
     */
    public function setIncremental(bool $incremental): void;
}
