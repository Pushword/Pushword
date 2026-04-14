<?php

namespace Pushword\Core\EventListener;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\Core\Service\PageOpenGraphImageGenerator;
use Pushword\Core\Service\TailwindGenerator;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::preRemove, entity: Page::class)]
#[AsEntityListener(event: Events::preUpdate, entity: Page::class)]
#[AsEntityListener(event: Events::prePersist, entity: Page::class)]
#[AsEntityListener(event: Events::postPersist, entity: Page::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Page::class)]
final class PageListener
{
    /** @var array<int, array{oldSlug: string, newSlug: string, host: string}> */
    private static array $pendingRedirects = [];

    private static bool $processingRedirects = false;

    public static bool $skipSlugChangeDetection = false;

    public function __construct(
        private readonly Security $security,
        private readonly PageOpenGraphImageGenerator $pageOpenGraphImageGenerator,
        private readonly TailwindGenerator $tailwindGenerator,
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

    public function postUpdate(Page $page, PostUpdateEventArgs $event): void
    {
        $this->tailwindGenerator->run($page);
        $this->processPendingRedirects($event);
    }

    public function preUpdate(Page $page, PreUpdateEventArgs $event): void
    {
        if (! $page->getSkipAutoTimestamp()) {
            $page->updatedAt = new DateTime();
        }

        $this->updatePageEditor($page);
        $this->pageOpenGraphImageGenerator->setPage($page)->generatePreviewImage();
        $this->detectSlugChange($page, $event);
    }

    private function updatePageEditor(Page $page): void
    {
        if (null === ($user = $this->security->getUser())) {
            return;
        }

        if (! $user instanceof User) {
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

    private function detectSlugChange(Page $page, PreUpdateEventArgs $event): void
    {
        if (self::$skipSlugChangeDetection) {
            return;
        }

        if (! $event->hasChangedField('slug')) {
            return;
        }

        $oldSlug = $event->getOldValue('slug');
        if (! \is_string($oldSlug) || '' === $oldSlug || $oldSlug === $page->slug) {
            return;
        }

        self::$pendingRedirects[] = [
            'oldSlug' => $oldSlug,
            'newSlug' => $page->slug,
            'host' => $page->host,
        ];
    }

    private function processPendingRedirects(PostUpdateEventArgs $event): void
    {
        if (self::$processingRedirects) {
            return;
        }

        $pending = self::$pendingRedirects;
        self::$pendingRedirects = [];

        if ([] === $pending) {
            return;
        }

        self::$processingRedirects = true;

        try {
            $em = $event->getObjectManager();

            foreach ($pending as $data) {
                // Don't create redirect if old slug is already taken by another page on this host
                $existing = $em->getRepository(Page::class)->findOneBy([
                    'slug' => $data['oldSlug'],
                    'host' => $data['host'],
                ]);
                if (null !== $existing) {
                    continue;
                }

                // Update existing redirects pointing to old slug → point to new slug (prevent chains)
                $chainingRedirects = $em->getRepository(Page::class)->createQueryBuilder('p')
                    ->where('p.host = :host')
                    ->andWhere('p.mainContent LIKE :target')
                    ->setParameter('host', $data['host'])
                    ->setParameter('target', 'Location: /'.$data['oldSlug'].' %')
                    ->getQuery()
                    ->getResult();

                foreach ($chainingRedirects as $chainingRedirect) {
                    $chainingRedirect->setMainContent('Location: /'.$data['newSlug'].' '.$chainingRedirect->getRedirectionCode());
                }

                $redirect = new Page(false);
                $redirect->host = $data['host'];
                $redirect->slug = $data['oldSlug'];
                $redirect->setMainContent('Location: /'.$data['newSlug'].' 301');

                $em->persist($redirect);
            }

            $em->flush();
        } finally {
            self::$processingRedirects = false;
        }
    }
}
