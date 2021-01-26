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

class Versionner implements EventSubscriber //EventSubscriberInterface
{
    private Filesystem $fileSystem;
    private string $logsDir;
    private string $pageClass;
    private EntityManagerInterface $entityManager;
    public static bool $version = true;
    private SerializerInterface $serializer;

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

    public function getSubscribedEvents()
    {
        // return the subscribed events, their methods and priorities
        return [
            Events::postPersist,
            Events::postUpdate,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->postUpdate($args);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        if (false === static::$version) {
            return;
        }

        $entity = $args->getObject();

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

        if (! $page) {
            throw new Exception('Page not found `'.$pageId.'`');
        }

        $this->populate($page, $page->getId(), $version);

        $this->entityManager->flush();

        static::$version = true;
    }

    /** @param PageInterface|string $id */
    public function populate(PageInterface $page, $id, string $version): PageInterface
    {
        $pageVersionned = $this->getPageVersion($id, $version);

        $this->serializer->deserialize($pageVersionned, \get_class($page), 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $page]);

        return $page;
    }

    /** @param PageInterface|string $page */
    private function getPageVersion($page, string $version): string
    {
        $versionFile = $this->getVersionFile($page, $version);
        $content = file_get_contents($versionFile);

        if (false === $content) {
            throw new Exception('Version not found');
        }

        return $content;
    }

    /** @param PageInterface|string $pageId */
    public function reset($pageId): void
    {
        $this->fileSystem->remove($this->getVersionDir($pageId));
    }

    /** @param PageInterface|string $page */
    public function getPageVersions($page): array
    {
        $dir = $this->getVersionDir($page);

        if (! file_exists($dir)) {
            return [];
        }

        $versions = array_filter(scandir($dir), function (string $item) {
            return ! \in_array($item, ['.', '..']);
        });

        return array_values($versions);
    }

    /** @param PageInterface|string $page */
    private function getVersionDir($page): string
    {
        return $this->logsDir.'/version/'.($page instanceof PageInterface ? (string) $page->getId() : $page);
    }

    /** @param PageInterface|string $page */
    private function getVersionFile($page, string $version = ''): string
    {
        return $this->getVersionDir($page).'/'.($version ?: uniqid());
    }

    private function getProperties(PageInterface $page): array
    {
        return Entity::getProperties($page);
    }
}
