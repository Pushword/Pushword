<?php

namespace Pushword\Admin;

use Pushword\Admin\DependencyInjection\AdminExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordAdminBundle extends Bundle
{
    public function getContainerExtension()
    {
        if (null === $this->extension) {
            $this->extension = new AdminExtension();
        }

        return $this->extension;
    }
}
