<?php

namespace Pushword\Admin;

use Pushword\Core\Component\App\AppPool;
use Sonata\AdminBundle\Admin\AdminInterface as AdminAdminInterface;
use Symfony\Component\Routing\RouterInterface;

interface AdminInterface extends AdminAdminInterface
{
    public function getRouter(): RouterInterface;

    public function getApps(): AppPool;

    public function getMessagePrefix(): string;

    public function getMediaClass(): string;

    public function getPageClass(): string;

    public function getUser();
}
