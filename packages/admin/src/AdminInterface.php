<?php

namespace Pushword\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Entity\UserInterface;
use Pushword\Core\Service\ImageManager;
use Sonata\AdminBundle\Admin\AdminInterface as AdminAdminInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as Twig;

/**
 * @template T of object
 *
 * @extends AdminAdminInterface<T>
 */
interface AdminInterface extends AdminAdminInterface
{
    public function getRouter(): RouterInterface;

    public function getApps(): AppPool;

    public function getMessagePrefix(): string;

    /**
     * @return class-string<MediaInterface>
     */
    public function getMediaClass(): string;

    /**
     * @return class-string<PageInterface>
     */
    public function getPageClass(): string;

    public function getUser(): UserInterface;

    public function getTwig(): Twig;

    public function getEntityManager(): EntityManagerInterface;

    public function getImageManager(): ImageManager;
}
