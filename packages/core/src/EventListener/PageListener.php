<?php

namespace Pushword\Core\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\PageInterface;
use Symfony\Component\Security\Core\Security;

class PageListener
{
    private Security $security;
    private EntityManagerInterface $entityManager;

    public function __construct(Security $security, EntityManagerInterface $entityManager)
    {
        $this->security = $security;
        $this->entityManager = $entityManager;
    }

    public function preRemove(PageInterface $page)
    {
        // method_exists($page, 'getChildrenPages') &&
        if (! $page->getChildrenPages()->isEmpty()) {
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
        /*
        HUGE BUG: une fois la page mise à jour avec ce code, impossible d'afficher la page d'édition
        de Admin sans être déconnecté
        if (!$user = $this->security->getUser())
            return;

        if (null === $page->getCreatedBy()) {
            $page->setCreatedBy($user);
        }

        if (!$page->getLastEditBy() || $page->getLastEditBy() !== $user)
            $page->setLastEditBy($user);
        */
        //$this->entityManager->flush();
        //$pageHasEditor = (new PageHasEditor())->setPage($page)->setEditor($user)->setEditedAt(new \DateTime());
        //$this->entityManager->persist($pageHasEditor);
        //$this->entityManager->flush();

        //$page->addPageHasEditor($pageHasEditor);
        //$this->entityManager->flush();
    }
}
