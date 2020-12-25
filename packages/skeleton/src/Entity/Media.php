<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\Media as BaseMedia;
use Pushword\Core\Repository\MediaRepository;

/**
 * @ORM\Entity(repositoryClass=MediaRepository::class)
 */
class Media extends BaseMedia
{
}
