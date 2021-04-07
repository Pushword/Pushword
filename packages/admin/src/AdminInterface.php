<?php

namespace Pushword\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Service\ImageManager;
use Sonata\AdminBundle\Admin\AdminInterface as AdminAdminInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as Twig;

interface AdminInterface extends AdminAdminInterface
{
    public function getRouter(): RouterInterface;

    public function getApps(): AppPool;

    public function getMessagePrefix(): string;

    public function getMediaClass(): string;

    public function getPageClass(): string;

    public function getUser();

    public function getTwig(): Twig;

    public function getEntityManager(): EntityManagerInterface;

    public function getImageManager(): ImageManager;
}
