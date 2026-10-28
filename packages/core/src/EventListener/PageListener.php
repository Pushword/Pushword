<?php

declare(strict_types=1);

namespace Pushword\Core\EventListener;

use Cocur\Slugify\Slugify;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
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
use Symfony\Component\Security\Core\User\UserInterface;
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
            foreach ($page->childrenPages as $childrenPage) {
                $childrenPage->parentPage = null;
            }
        }

        // Auto-promote a variant when its master is removed (no orphans, self-FK kept).
        $this->variantManager->promoteOnMasterRemoval($page);
    }

    public function prePersist(Page $page, PrePersistEventArgs $event): void
    {
        $page->initTimestampableProperties();
        $this->setSlugIfNotDefined($page, $event);
        $this->setLocaleIfNotDefined($page);
        $this->updatePageEditor($page, creating: true);
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
        if (! $page->skipAutoTimestamp) {
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

    /**
     * `createdBy` is only ever set at creation: backfilling it on update made whoever
     * first saved a page authored elsewhere — imported by the flat sync, written through
     * the API — its recorded creator, a claim nothing else contradicted.
     */
    private function updatePageEditor(Page $page, bool $creating = false): void
    {
        if (! ($user = $this->security->getUser()) instanceof UserInterface) {
            return;
        }

        if (! $user instanceof User) {
            return;
        }

        if ($creating && ! $page->createdBy instanceof User) {
            $page->createdBy = $user;
        }

        if ($page->editedBy?->id !== $user->id) {
            $page->editedBy = $user;
        }
    }

    /**
     * The admin leaves the slug optional. A page saved without one used to get a
     * random hash, which went live as an unreadable URL (`/3415c783`): it now takes
     * its H1, or failing that its title, cut to leave room for a suffix in the 150-char
     * column, then suffixed `-2`, `-3`… until no page of its host uses it. The hash
     * remains for a page where neither yields a slug.
     */
    private function setSlugIfNotDefined(Page $page, PrePersistEventArgs $event): void
    {
        if ('' !== $page->slug) {
            return;
        }

        $slugify = new Slugify();
        $base = $slugify->slugify($page->h1) ?: $slugify->slugify($page->title);
        $base = rtrim(substr($base, 0, 140), '-');
        if ('' === $base) {
            $page->slug = substr(sha1(uniqid().random_int(0, mt_getrandmax())), 0, 8);

            return;
        }

        $taken = $this->slugsTakenOnHost($event->getObjectManager(), $page->host, $base);
        $slug = $base;
        for ($suffix = 2; isset($taken[$slug]); ++$suffix) {
            $slug = $base.'-'.$suffix;
        }

        $page->slug = $slug;
    }

    /**
     * The stored slugs of `$host` that are `$base` or `$base-…`, plus those of its pages
     * waiting in the same flush, which the query cannot see: two slug-less pages flushed
     * together would otherwise get the same slug.
     *
     * @return array<string, true>
     */
    private function slugsTakenOnHost(EntityManagerInterface $em, string $host, string $base): array
    {
        // Slugify only emits [a-z0-9-], so the base holds no LIKE wildcard.
        /** @var list<string> $storedSlugs */
        $storedSlugs = $em->getRepository(Page::class)->createQueryBuilder('p')
            ->select('p.slug')
            ->where('p.host = :host')
            ->andWhere('p.slug = :base OR p.slug LIKE :suffixed')
            ->setParameter('host', $host)
            ->setParameter('base', $base)
            ->setParameter('suffixed', $base.'-%')
            ->getQuery()
            ->getSingleColumnResult();
        $taken = array_fill_keys($storedSlugs, true);

        foreach ($em->getUnitOfWork()->getScheduledEntityInsertions() as $scheduled) {
            if ($scheduled instanceof Page && $scheduled->host === $host) {
                $taken[$scheduled->slug] = true;
            }
        }

        return $taken;
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

        $page->locale = $this->apps->get($page->host)->locale;
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
                    $chainingRedirect->mainContent = 'Location: /'.$data['newSlug'].' '.$chainingRedirect->getRedirectionCode();
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
