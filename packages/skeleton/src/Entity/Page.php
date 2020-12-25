<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\Page as BasePage;
use Pushword\Core\Repository\PageRepositoryInterface;

/**
 * @ORM\Entity(repositoryClass=PageRepositoryInterface::class)
 */
class Page extends BasePage
{
}
