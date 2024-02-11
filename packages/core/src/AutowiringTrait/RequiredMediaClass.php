<?php

namespace Pushword\Core\AutowiringTrait;

use Pushword\Core\Entity\MediaInterface;
use Symfony\Contracts\Service\Attribute\Required;

trait RequiredMediaClass
{
    /**
     * @var class-string<MediaInterface>
     */
    private string $mediaClass;

    /**
     * @param class-string<MediaInterface> $mediaClass
     */
    #[Required]
    public function setMediaClass(string $mediaClass): self
    {
        $this->mediaClass = $mediaClass;

        return $this;
    }

    /**
     * @return class-string<MediaInterface>
     */
    public function getMediaClass(): string
    {
        return $this->mediaClass;
    }
}
