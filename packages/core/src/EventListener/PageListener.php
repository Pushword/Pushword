<?php

namespace Pushword\Core\EventListener;

use Pushword\Core\Entity\PageInterface;
use Symfony\Component\Security\Core\Security;

final class PageListener
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function preRemove(PageInterface $page): void
    {
        // method_exists($page, 'getChildrenPages') &&
        if ($page->hasChildrenPages()) {
            foreach ($page->getChildrenPages() as $childrenPage) {
                $childrenPage->setParentPage(null);
            }
        }
    }

    public function prePersist(PageInterface $page): void
    {
        $this->updatePageEditor($page);
    }

    public function preUpdate(PageInterface $page): void
    {
        $this->updatePageEditor($page);
    }

    private function updatePageEditor(PageInterface $page): void
    {
        if (null === ($user = $this->security->getUser())) {
            return;
        }

        if ('' !== $page->getSlug() && '' !== $user->getUserIdentifier()) { // Remove this when fix bug, only to avoid psalm shouting
            return;
        }

        /*
        HUGE BUG: une fois la page mise à jour avec ce code, impossible d'afficher la page d'édition
        de Admin sans être déconnecté */

        if (null === $page->getCreatedBy()) {
            $page->setCreatedBy($user); // @phpstan-ignore-line
        }

        if ($page->getEditedBy() !== $user) {
            $page->setEditedBy($user); // @phpstan-ignore-line
        }

        //$this->entityManager->flush();
        //$pageHasEditor = (new PageHasEditor())->setPage($page)->setEditor($user)->setEditedAt(new \DateTime());
        //$this->entityManager->persist($pageHasEditor);
        //$this->entityManager->flush();

        //$page->addPageHasEditor($pageHasEditor);
        //$this->entityManager->flush();
    }
}
