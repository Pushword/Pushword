<?php

namespace Pushword\Core\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\PageTrait\PageEditorTrait;
use Pushword\Core\Entity\PageTrait\PageExtendedTrait;
use Pushword\Core\Entity\PageTrait\PageI18nTrait;
use Pushword\Core\Entity\PageTrait\PageMainImageTrait;
use Pushword\Core\Entity\PageTrait\PageOpenGraphTrait;
use Pushword\Core\Entity\PageTrait\PageParentTrait;
use Pushword\Core\Entity\PageTrait\PageRedirectionTrait;
use Pushword\Core\Entity\PageTrait\PageSearchTrait;
use Pushword\Core\Entity\PageTrait\PageTrait;
use Pushword\Core\Entity\PageTrait\PageTwitterCardTrait;
use Pushword\Core\Entity\SharedTrait\CustomPropertiesTrait;
use Pushword\Core\Entity\SharedTrait\HostTrait;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
use Pushword\Core\Validator\Constraints\PageRendering;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @ORM\MappedSuperclass
 * @ORM\HasLifecycleCallbacks
 * @UniqueEntity(
 *     fields={"host", "slug"},
 *     errorPath="slug",
 *     message="page.slug.already_used"
 * )
 */
class Page implements PageInterface
{
    use CustomPropertiesTrait;
    use HostTrait;
    use IdTrait;
    use PageEditorTrait;
    use PageExtendedTrait;
    use PageI18nTrait;
    use PageMainImageTrait;
    use PageOpenGraphTrait;
    use PageParentTrait;
    use PageRedirectionTrait;
    use PageSearchTrait;
    use PageTrait;
    use PageTwitterCardTrait;
    use TimestampableTrait;

    public function __construct(bool $initDateTimeProperties = true)
    {
        if ($initDateTimeProperties) {
            $this->initTimestampableProperties();
            $this->publishedAt = new \DateTime();
        }
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        // TODO : fix why on admin, it's not throwing exception on submit
        //$metadata->addConstraint( new PageRendering());
    }
}
