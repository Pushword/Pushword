<?php

namespace Pushword\Core\EventListener;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\PageOpenGraphImageGenerator;
use Pushword\Core\Service\TailwindGenerator;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::preRemove, entity: Page::class)]
#[AsEntityListener(event: Events::preUpdate, entity: Page::class)]
#[AsEntityListener(event: Events::prePersist, entity: Page::class)]
#[AsEntityListener(event: Events::postPersist, entity: Page::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Page::class)]
final readonly class PageListener
{
    public function __construct(
        private Security $security,
        private PageOpenGraphImageGenerator $pageOpenGraphImageGenerator,
        private TailwindGenerator $tailwindGenerator,
    ) {
    }

    public function preRemove(Page $page): void
    {
        // method_exists($page, 'getChildrenPages') &&
        if ($page->hasChildrenPages()) {
            foreach ($page->getChildrenPages() as $childrenPage) {
                $childrenPage->setParentPage(null);
            }
        }
    }

    public function prePersist(Page $page): void
    {
        $page->initTimestampableProperties();
        $this->setIdAsSlugIfNotDefined($page);
        $this->updatePageEditor($page);
        $this->pageOpenGraphImageGenerator->setPage($page)->generatePreviewImage();
    }

    public function postPersist(Page $page): void
    {
        $this->tailwindGenerator->run($page);
    }

    public function postUpdate(Page $page): void
    {
        $this->tailwindGenerator->run($page);
    }

    public function preUpdate(Page $page): void
    {
        $page->updatedAt = new DateTime();
        $this->updatePageEditor($page);
        $this->pageOpenGraphImageGenerator->setPage($page)->generatePreviewImage();
    }

    private function updatePageEditor(Page $page): void
    {
        if (null === ($user = $this->security->getUser())) {
            return;
        }

        if (! $user instanceof \Pushword\Core\Entity\User) {
            return;
        }

        if (null === $page->createdBy) {
            $page->createdBy = $user;
        }

        if ($page->editedBy?->id !== $user->id) {
            $page->editedBy = $user;
        }
    }

    public function setIdAsSlugIfNotDefined(Page $page): void
    {
        if ('' === $page->getSlug()) {
            $page->setSlug(substr(sha1(uniqid().random_int(0, mt_getrandmax())), 0, 8));
        }
    }
}
