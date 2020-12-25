<?php

namespace Pushword\PageScanner;

use Pushword\PageScanner\DependencyInjection\PageScannerExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordPageScannerBundle extends Bundle
{
    public function getContainerExtension()
    {
        if (null === $this->extension) {
            $this->extension = new PageScannerExtension();
        }

        return $this->extension;
    }
}
