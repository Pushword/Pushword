<?php

namespace Pushword\Version;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Exception;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\Repository;
use Pushword\Core\Utils\Entity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class Versionner implements EventSubscriber // EventSubscriberInterface
{
    private Filesystem $fileSystem;

    private string $logsDir;

    /**
     * @var class-string<PageInterface>
     */
    private string $pageClass;

    private EntityManagerInterface $entityManager;

    public static bool $version = true;

    private SerializerInterface $serializer;

    /**
     * @param class-string<PageInterface> $pageClass
     */
    public function __construct(
        string $logDir,
        string $pageClass,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer
    ) {
        $this->logsDir = $logDir;
        $this->pageClass = $pageClass;
        $this->fileSystem = new Filesystem();
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
    }

    /**
     * @return string[]
     */
    public function getSubscribedEvents(): array
    {
        // return the subscribed events, their methods and priorities
        return [
            Events::postPersist,
            Events::postUpdate,
        ];
    }

    public function postPersist(LifecycleEventArgs $lifecycleEventArgs): void
    {
        $this->postUpdate($lifecycleEventArgs);
    }

    public function postUpdate(LifecycleEventArgs $lifecycleEventArgs): void
    {
        if (! static::$version) {
            return;
        }

        $entity = $lifecycleEventArgs->getObject();

        if (! $entity instanceof PageInterface) {
            return;
        }

        $this->createVersion($entity);
    }

    private function createVersion(PageInterface $page): void
    {
        $versionFile = $this->getVersionFile($page);

        $jsonContent = $this->serializer
            ->serialize($page, 'json', [AbstractNormalizer::ATTRIBUTES => $this->getProperties($page)]);

        $this->fileSystem->dumpFile($versionFile, $jsonContent);
    }

    public function loadVersion(string $pageId, string $version): void
    {
        static::$version = false;

        $page = Repository::getPageRepository($this->entityManager, $this->pageClass)->findOneBy(['id' => $pageId]);

        if (null === $page) {
            throw new Exception('Page not found `'.$pageId.'`');
        }

        $this->populate($page, $version);

        $this->entityManager->flush();

        static::$version = true;
    }

    public function populate(PageInterface $page, string $version, ?int $pageId = null): PageInterface
    {
        $pageVersionned = $this->getPageVersion(null !== $pageId ? $pageId : $page, $version);

        $this->serializer->deserialize($pageVersionned, \get_class($page), 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $page]);

        return $page;
    }

    /** @param PageInterface|int $page */
    private function getPageVersion($page, string $version): string
    {
        $versionFile = $this->getVersionFile($page, $version);

        return \Safe\file_get_contents($versionFile);
    }

    /** @param PageInterface|int $pageId */
    public function reset($pageId): void
    {
        $this->fileSystem->remove($this->getVersionDir($pageId));
    }

    /**
     * @param PageInterface|int $page
     *
     * @return string[]
     */
    public function getPageVersions($page): array
    {
        $dir = $this->getVersionDir($page);

        if (! file_exists($dir) || ! \is_array($scandir = \Safe\scandir($dir))) {
            return [];
        }

        $versions = array_filter($scandir, fn (string $item): bool => ! \in_array($item, ['.', '..'], true));

        return array_values($versions);
    }

    /** @param PageInterface|int $page */
    private function getVersionDir($page): string
    {
        $pageId = ($page instanceof PageInterface ? (string) $page->getId() : $page);

        return $this->logsDir.'/version/'.$pageId;
    }

    /** @param PageInterface|int $page */
    private function getVersionFile($page, ?string $version = null): string
    {
        return $this->getVersionDir($page).'/'.($version ?? uniqid());
    }

    /**
     * @return array<string>
     */
    private function getProperties(PageInterface $page): array
    {
        return Entity::getProperties($page);
    }
}
