<?php

namespace Pushword\Core\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Service\PageOpenGraphImageGenerator;
use Pushword\Core\Service\TailwindGenerator;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::preRemove, entity: '%pw.entity_page%')]
#[AsEntityListener(event: Events::preUpdate, entity: '%pw.entity_page%')]
#[AsEntityListener(event: Events::prePersist, entity: '%pw.entity_page%')]
#[AsEntityListener(event: Events::postPersist, entity: '%pw.entity_page%')]
#[AsEntityListener(event: Events::postUpdate, entity: '%pw.entity_page%')]
final readonly class PageListener
{
    public function __construct(
        private Security $security,
        private PageOpenGraphImageGenerator $pageOpenGraphImageGenerator,
        private TailwindGenerator $tailwindGenerator,
    ) {
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
        $this->setIdAsSlugIfNotDefined($page);
        $this->updatePageEditor($page);
        $this->pageOpenGraphImageGenerator->setPage($page)->generatePreviewImage();
    }

    public function postPersist(PageInterface $page): void
    {
        $this->tailwindGenerator->run($page);
    }

    public function postUpdate(PageInterface $page): void
    {
        $this->tailwindGenerator->run($page);
    }

    public function preUpdate(PageInterface $page): void
    {
        $this->updatePageEditor($page);
        $this->pageOpenGraphImageGenerator->setPage($page)->generatePreviewImage();
    }

    /**
     * @psalm-suppress UnevaluatedCode
     * @psalm-suppress UnusedVariable
     * @psalm-suppress UnusedParam
     */
    private function updatePageEditor(PageInterface $page): void
    {
        if (null === ($user = $this->security->getUser())) {
            return;
        }

        //  Remove this when fix bug, only to avoid psalm shouting
        return;

        /*
        HUGE BUG: une fois la page mise à jour avec ce code, impossible d'afficher la page d'édition
        de Admin sans être déconnecté

        if (null === $page->getCreatedBy()) {
            $page->setCreatedBy($user);
        }

        if ($page->getEditedBy()->getId() !== $user->getId()) {
            $page->setEditedBy($user);
        }
        /**/

        // $this->entityManager->flush();
        // $pageHasEditor = (new PageHasEditor())->setPage($page)->setEditor($user)->setEditedAt(new \DateTime());
        // $this->entityManager->persist($pageHasEditor);
        // $this->entityManager->flush();

        // $page->addPageHasEditor($pageHasEditor);
        // $this->entityManager->flush();
    }

    public function setIdAsSlugIfNotDefined(PageInterface $page): void
    {
        if ('' === $page->getSlug()) {
            $page->setSlug(substr(sha1(uniqid().random_int(0, mt_getrandmax())), 0, 8));
        }
    }
}
