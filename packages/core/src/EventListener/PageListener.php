<?php

namespace Pushword\Core\EventListener;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\Core\Service\PageOpenGraphImageGenerator;
use Pushword\Core\Service\TailwindGenerator;
use Pushword\Core\Service\VariantManager;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\ResetInterface;

#[AsEntityListener(event: Events::preRemove, entity: Page::class)]
#[AsEntityListener(event: Events::preUpdate, entity: Page::class)]
#[AsEntityListener(event: Events::prePersist, entity: Page::class)]
#[AsEntityListener(event: Events::postPersist, entity: Page::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Page::class)]
final class PageListener implements ResetInterface
{
    /** @var array<int, array{oldSlug: string, newSlug: string, host: string}> */
    private static array $pendingRedirects = [];

    private static bool $processingRedirects = false;

    public static bool $skipSlugChangeDetection = false;

    public function __construct(
        private readonly Security $security,
        private readonly PageOpenGraphImageGenerator $pageOpenGraphImageGenerator,
        private readonly TailwindGenerator $tailwindGenerator,
        private readonly PageCacheSuppressor $cacheSuppressor,
        private readonly VariantManager $variantManager,
        private readonly SiteRegistry $apps,
    ) {
    }

    /**
     * Worker-mode safety (kernel.reset): these statics are process-global and
     * normally drained within the flush that fills them. But a slug change queued
     * in preUpdate is only consumed in postUpdate, so a flush that throws in
     * between would orphan a pending redirect — replayed during a later request in
     * a long-lived worker as a phantom redirect for a slug change that rolled back.
     * Clearing every boundary neutralizes that (and any flag a caller left set on a
     * fatal). In classic FPM the process dies between requests, so this is a no-op.
     */
    public function reset(): void
    {
        self::$pendingRedirects = [];
        self::$processingRedirects = false;
        self::$skipSlugChangeDetection = false;
    }

    public function preRemove(Page $page): void
    {
        // method_exists($page, 'getChildrenPages') &&
        if ($page->hasChildrenPages()) {
            foreach ($page->getChildrenPages() as $childrenPage) {
                $childrenPage->setParentPage(null);
            }
        }

        // Auto-promote a variant when its master is removed (no orphans, self-FK kept).
        $this->variantManager->promoteOnMasterRemoval($page);
    }

    public function prePersist(Page $page): void
    {
        $page->initTimestampableProperties();
        $this->setIdAsSlugIfNotDefined($page);
        $this->setLocaleIfNotDefined($page);
        $this->updatePageEditor($page);
        $this->generateOpenGraphImage($page);
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
        $this->generateOpenGraphImage($page);
        $this->detectSlugChange($page, $event);
    }

    /**
     * Generate the page's Open Graph preview image, unless a bulk operation is in
     * progress (e.g. flat import): generating one Imagick image per page leaks
     * gigabytes of off-heap ImageMagick memory across thousands of pages. When
     * suppressed, the image is regenerated lazily on first render via MediaExtension.
     */
    private function generateOpenGraphImage(Page $page): void
    {
        if ($this->cacheSuppressor->isSuppressed()) {
            return;
        }

        $this->pageOpenGraphImageGenerator->setPage($page)->generatePreviewImage();
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

    /**
     * A page created from the admin (PageCrudController::initializeNewPage) or imported
     * by the flat sync (PageImporter) gets the site's locale; one written through the API
     * only got one when the payload happened to carry `locale:`, so it landed with ''.
     * Every read path then patched it back in-memory (PageResolver) without ever fixing
     * the row. Enforce the invariant here so all write paths agree.
     *
     * Resolved from the page's own host: on a multi-host install each host declares its
     * own locale, and the write may well happen under a different one.
     */
    private function setLocaleIfNotDefined(Page $page): void
    {
        if ('' !== $page->locale) {
            return;
        }

        $page->locale = $this->apps->get($page->host)->getLocale();
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

            $repository = $em->getRepository(Page::class);

            foreach ($pending as $data) {
                // Don't create redirect if old slug is already taken by another page on this host
                $existing = $repository->findOneBy([
                    'slug' => $data['oldSlug'],
                    'host' => $data['host'],
                ]);
                if (null !== $existing) {
                    continue;
                }

                // Repoint legacy phantom redirects that targeted the old slug → new slug (prevent chains)
                $chainingRedirects = $repository->createQueryBuilder('p')
                    ->where('p.host = :host')
                    ->andWhere('p.mainContent LIKE :target')
                    ->setParameter('host', $data['host'])
                    ->setParameter('target', 'Location: /'.$data['oldSlug'].' %')
                    ->getQuery()
                    ->getResult();

                foreach ($chainingRedirects as $chainingRedirect) {
                    $chainingRedirect->setMainContent('Location: /'.$data['newSlug'].' '.$chainingRedirect->getRedirectionCode());
                }

                // Record the old slug next to the content, on the destination page's redirectFrom.
                $destination = $repository->findOneBy([
                    'slug' => $data['newSlug'],
                    'host' => $data['host'],
                ]);
                if (null !== $destination) {
                    $destination->addRedirectFrom($data['oldSlug']);
                    $em->persist($destination);
                }
            }

            $em->flush();
        } finally {
            self::$processingRedirects = false;
        }
    }
}
