<?php

namespace Pushword\StaticGenerator\Generator;

class CNAMEGenerator extends AbstractGenerator
{
    public function generate(string $host = null): void
    {
        parent::generate($host);

        $this->filesystem->dumpFile($this->getStaticDir().'/CNAME', $this->app->getMainHost());
    }
}
