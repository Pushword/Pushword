<?php

namespace Pushword\Core\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Pushword\Core\Entity\SharedTrait\CustomPropertiesInterface;
use Pushword\Core\Entity\SharedTrait\HostInterface;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\TimestampableInterface;

interface PageInterface extends HostInterface, IdInterface, TimestampableInterface, CustomPropertiesInterface
{
    // PageTrait
    public function getSlug(): string;

    public function getRealSlug(): string;

    public function setSlug($slug, $set = false): self;

    public function getMainContent(): string;

    public function setMainContent($mainContent): self;

    // ---

    // ParentTrait
    public function getParentPage(): ?self;

    public function setParentPage(?self $parentPage): self;

    /**
     * @return ArrayCollection|PageInterface[]
     */
    public function getChildrenPages();

    // ImageTrait
    public function resetPageHasMedias(): void;

    public function removePageHasMedia(PageHasMedia $pageHasMedia): void;

    public function addPageHasMedia(PageHasMedia $pageHasMedia): self;

    // ---

    public function getRedirection();

    public function getRedirectionCode();

    public function getCreatedAt();

    public function getTemplate();

    public function getH1();

    public function getTitle();

    public function getName();

    // PageI18n
    public function getLocale();

    public function setLocale($locale);

    // Page Extended
    public function getExtendedPage(): ?self;

    public function setExtendPage(?self $extendedPage): self;
}
