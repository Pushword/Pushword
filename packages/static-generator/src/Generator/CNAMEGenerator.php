<?php

namespace Pushword\StaticGenerator\Generator;

use Override;

class CNAMEGenerator extends AbstractGenerator
{
    #[Override]
    public function generate(?string $host = null): void
    {
        parent::generate($host);

        $this->filesystem->dumpFile($this->getStaticDir().'/CNAME', $this->app->getMainHost());
    }
}
