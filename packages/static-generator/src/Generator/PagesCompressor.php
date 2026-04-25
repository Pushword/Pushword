<?php

namespace Pushword\StaticGenerator\Generator;

use Override;

class PagesCompressor extends AbstractGenerator
{
    #[Override]
    public function generate(?string $host = null): void
    {
        // this generator is doing nothing
        // except permitting to launch async compression in PageGenerator
        // by checking if this Generator is in used
    }
}
