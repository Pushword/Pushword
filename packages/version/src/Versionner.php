<?php

namespace Pushword\Version;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Column;
use Exception;
use Pushword\Core\Entity\Page;
// use Doctrine\ORM\Event\LifecycleEventArgs;
use Pushword\Core\Utils\Entity;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
class Versionner
{
    private readonly Filesystem $fileSystem;

    public static bool $version = true;

    public function __construct(
        private readonly string $logDir,
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer
    ) {
        $this->fileSystem = new Filesystem();
    }

    public function postPersist(PostPersistEventArgs $lifecycleEventArgs): void
    {
        $this->postUpdate($lifecycleEventArgs);
    }

    public function postUpdate(PostPersistEventArgs|PostUpdateEventArgs $lifecycleEventArgs): void
    {
        if (! static::$version) {
            return;
        }

        $entity = $lifecycleEventArgs->getObject();

        if (! $entity instanceof Page) {
            return;
        }

        $this->createVersion($entity);
    }

    private function createVersion(Page $page): void
    {
        $versionFile = $this->getVersionFile($page);

        $jsonContent = $this->serializer
            ->serialize($page, 'json', [AbstractNormalizer::ATTRIBUTES => $this->getProperties($page)]);

        $this->fileSystem->dumpFile($versionFile, $jsonContent);
    }

    public function loadVersion(string $pageId, string $version): void
    {
        static::$version = false;

        try {
            $page = $this->entityManager->getRepository(Page::class)->find($pageId);

            if (! $page instanceof Page) {
                throw new Exception('Page not found `'.$pageId.'`');
            }

            $this->populate($page, $version);

            $this->entityManager->flush();
        } finally {
            static::$version = true;
        }
    }

    public function populate(Page $page, string $version, ?int $pageId = null): Page
    {
        $pageVersionned = $this->getPageVersion($pageId ?? $page, $version);

        $this->serializer->deserialize($pageVersionned, $page::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $page,
            ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true, // permits to import tags as string
        ]);

        return $page;
    }

    private function getPageVersion(int|Page $page, string $version): string
    {
        $versionFile = $this->getVersionFile($page, $version);

        return $this->fileSystem->readFile($versionFile);
    }

    public function reset(int|Page $pageId): void
    {
        $this->fileSystem->remove($this->getVersionDir($pageId));
    }

    /**
     * @return string[]
     */
    public function getPageVersions(int|Page $page): array
    {
        $dir = $this->getVersionDir($page);
        if (! $this->fileSystem->exists($dir)) {
            return [];
        }

        /** @var string[] */
        $scandir = scandir($dir);

        $versions = array_filter($scandir, static fn (string $item): bool => ! \in_array($item, ['.', '..'], true));

        return array_values($versions);
    }

    private function getVersionDir(int|Page $page): string
    {
        $pageId = ($page instanceof Page ? (string) $page->id : $page);

        return $this->logDir.'/version/'.$pageId;
    }

    private function getVersionFile(int|Page $page, ?string $version = null): string
    {
        return $this->getVersionDir($page).'/'.($version ?? uniqid());
    }

    /**
     * @return array<string>
     */
    private function getProperties(Page $page): array
    {
        return Entity::getProperties($page, [Column::class]);
    }
}
