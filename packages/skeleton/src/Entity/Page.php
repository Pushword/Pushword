<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\Page as BasePage;
use Pushword\Core\Repository\PageRepository;

#[ORM\Entity(repositoryClass: PageRepository::class)]
class Page extends BasePage
{
}
