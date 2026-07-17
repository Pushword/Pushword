<?php

namespace Pushword\Core\Tests\Controller;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[Group('integration')]
final class MediaRepositoryTest extends KernelTestCase
{
    public function testFindDuplicate(): void
    {
        $repo = $this->getMediaRepository();

        $duplicate = $repo->findDuplicate(new Media()->setHash('testFakeHash'));
        self::assertNull($duplicate);

        $duplicate = $repo->findDuplicate($this->getMediaToTestDuplicate());
        self::assertInstanceOf(Media::class, $duplicate);
    }

    public function testFindOneBySearchMatchesFileName(): void
    {
        $repo = $this->getMediaRepository();

        $result = $repo->findOneBySearch('1.jpg');

        self::assertNotNull($result);
        self::assertSame('1.jpg', $result->getFileName());
    }

    public function testFindOneBySearchMatchesAlt(): void
    {
        $repo = $this->getMediaRepository();

        $result = $repo->findOneBySearch('Demo 1');

        self::assertNotNull($result);
        self::assertStringContainsString('Demo', $result->getAlt());
    }

    public function testFindOneBySearchMatchesPartialFileName(): void
    {
        $repo = $this->getMediaRepository();

        $result = $repo->findOneBySearch('piedweb-logo');

        self::assertNotNull($result);
        self::assertSame('piedweb-logo.png', $result->getFileName());
    }

    public function testFindOneBySearchReturnsNullForNoMatch(): void
    {
        $repo = $this->getMediaRepository();

        $result = $repo->findOneBySearch('zzz_nonexistent_file_xyz');

        self::assertNull($result);
    }

    public function testFindOneBySearchFileNamePriorityOverAlt(): void
    {
        $repo = $this->getMediaRepository();

        // 'logo' appears in fileName 'piedweb-logo.png' and 'logo.svg'
        // The method should return a result matching via fileName first
        $result = $repo->findOneBySearch('logo');

        self::assertNotNull($result);
        self::assertStringContainsString('logo', $result->getFileName());
    }

    public function testFindOneByFileNameUsesLightIndex(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $repo = $em->getRepository(Media::class);

        $em->clear();
        $repo->resetFileNameIndexLight();

        $media = $repo->findOneByFileName('1.jpg');
        self::assertNotNull($media);
        self::assertSame('1.jpg', $media->getFileName());

        // Second call is served from the in-process index.
        self::assertSame($media, $repo->findOneByFileName('1.jpg'));

        // Unknown filename returns null (before and after warmup).
        self::assertNull($repo->findOneByFileName('zzz_nonexistent_file_xyz.jpg'));
    }

    public function testFindOneByFileNameOrHistoryFallsBackToHistory(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $repo = $em->getRepository(Media::class);

        $source = $repo->findOneByFileName('1.jpg');
        self::assertNotNull($source);
        $originalFileName = $source->getFileName();
        $renamedFileName = 'renamed-for-history-test.jpg';

        $source->setFileName($renamedFileName);
        self::assertContains($originalFileName, $source->getFileNameHistory());
        $em->flush();

        try {
            // Exact match on current filename works.
            $byCurrent = $repo->findOneByFileNameOrHistory($renamedFileName);
            self::assertNotNull($byCurrent);
            self::assertSame($source->id, $byCurrent->id);

            // History fallback resolves the old filename to the same entity.
            $byHistory = $repo->findOneByFileNameOrHistory($originalFileName);
            self::assertNotNull($byHistory);
            self::assertSame($source->id, $byHistory->id);

            // Unknown filename returns null.
            self::assertNull($repo->findOneByFileNameOrHistory('nope-history.jpg'));
        } finally {
            $source->setFileName($originalFileName);
            $source->setFileNameHistory([]);
            $em->flush();
        }
    }

    public function testLookupStillResolvesAfterOnClear(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $repo = $em->getRepository(Media::class);

        self::assertNotNull($repo->findOneByFileName('1.jpg'));

        $em->clear();
        // The index survives a clear (it holds scalars), and the ids it carries
        // still resolve against a cleared entity manager.
        self::assertNotNull($repo->findOneByFileName('1.jpg'));
    }

    public function testPersistentCacheHitAcrossInstances(): void
    {
        self::bootKernel();
        self::getContainer()->get('doctrine.orm.default_entity_manager');
        $registry = self::getContainer()->get(ManagerRegistry::class);
        $cache = new ArrayAdapter();

        // First repo: cold cache pool. Warms the index and writes it to the pool.
        $repoA = new MediaRepository($registry, $cache, debug: false);
        self::assertNotNull($repoA->findOneByFileName('1.jpg'));

        $versionItem = $cache->getItem(MediaRepository::VERSION_CACHE_KEY);
        $versionValue = $versionItem->isHit() ? $versionItem->get() : 0;
        $version = \is_int($versionValue) ? $versionValue : 0;
        self::assertTrue($cache->hasItem(MediaRepository::INDEX_CACHE_KEY_PREFIX.$version));

        // Second repo: fresh in-process state, warm cache pool.
        // Reads the index back from cache.app and still resolves filenames.
        $repoB = new MediaRepository($registry, $cache, debug: false);
        $media = $repoB->findOneByFileName('1.jpg');
        self::assertNotNull($media);
        self::assertSame('1.jpg', $media->getFileName());
    }

    /**
     * The index survives a clear, so the version counter is what keeps a
     * long-lived worker honest about writes made by another process.
     */
    public function testOnClearRechecksVersion(): void
    {
        self::bootKernel();
        self::getContainer()->get('doctrine.orm.default_entity_manager');
        $registry = self::getContainer()->get(ManagerRegistry::class);
        $cache = new ArrayAdapter();

        $repo = new MediaRepository($registry, $cache, debug: false);
        $media = $repo->findOneByFileName('1.jpg'); // warms the index at version 0
        self::assertNotNull($media);

        // Another process writes a media: it publishes an index at the next
        // version and bumps the shared counter. Nothing resets us in-process.
        $indexItem = $cache->getItem(MediaRepository::INDEX_CACHE_KEY_PREFIX.'1');
        $indexItem->set(['written-elsewhere.jpg' => ['id' => $media->id, 'fileName' => 'written-elsewhere.jpg', 'fileNameHistory' => []]]);

        $cache->save($indexItem);

        $versionItem = $cache->getItem(MediaRepository::VERSION_CACHE_KEY);
        $versionItem->set(1);

        $cache->save($versionItem);

        $repo->onClear();

        self::assertNotNull(
            $repo->findOneByFileName('written-elsewhere.jpg'),
            'onClear must re-check the version, so a write from another process is picked up',
        );
    }

    /**
     * The point of keeping the index across a clear: an unchanged version must
     * not cost a rebuild. Guards the pw:static win, which no other test would
     * notice disappearing.
     */
    public function testOnClearReusesIndexWhenVersionUnchanged(): void
    {
        self::bootKernel();
        self::getContainer()->get('doctrine.orm.default_entity_manager');
        $registry = self::getContainer()->get(ManagerRegistry::class);
        $cache = new ArrayAdapter();

        $repo = new MediaRepository($registry, $cache, debug: false);
        self::assertNotNull($repo->findOneByFileName('1.jpg')); // warms at version 0

        // Poison the pool at the version already in use: only a rebuild — the
        // thing this must avoid — would read it back.
        $indexItem = $cache->getItem(MediaRepository::INDEX_CACHE_KEY_PREFIX.'0');
        $indexItem->set(['rebuilt-marker.jpg' => ['id' => 1, 'fileName' => 'rebuilt-marker.jpg', 'fileNameHistory' => []]]);

        $cache->save($indexItem);

        $repo->onClear();

        self::assertNull(
            $repo->findOneByFileName('rebuilt-marker.jpg'),
            'onClear must keep the index when the version is unchanged, not requery',
        );
        self::assertNotNull($repo->findOneByFileName('1.jpg'));
    }

    public function testSelfHealsStaleIndexId(): void
    {
        self::bootKernel();
        self::getContainer()->get('doctrine.orm.default_entity_manager');
        $registry = self::getContainer()->get(ManagerRegistry::class);
        $cache = new ArrayAdapter();

        // Poison the persisted index so '1.jpg' maps to an id that does not exist
        // in the DB — what a worker reads when media changed out of band (bulk
        // import, DB restore) without bumping the version counter. version=0 since
        // no version item is set, so this is the key warmup() will read.
        $indexItem = $cache->getItem(MediaRepository::INDEX_CACHE_KEY_PREFIX.'0');
        $indexItem->set(['1.jpg' => ['id' => 999999, 'fileName' => '1.jpg', 'fileNameHistory' => []]]);

        $cache->save($indexItem);

        $repo = new MediaRepository($registry, $cache, debug: false);

        // Without self-healing this returns null and rendering throws
        // "Internal - Can't handle the value submitted". It must rebuild and resolve.
        $media = $repo->findOneByFileName('1.jpg');
        self::assertNotNull($media, 'stale cached index id must self-heal via a rebuild');
        self::assertSame('1.jpg', $media->getFileName());

        // A fresh instance reads the still-poisoned cache (the heal is in-process,
        // not written back) — findOneByFileNameOrHistory must self-heal too.
        $repoFresh = new MediaRepository($registry, $cache, debug: false);
        $viaHistory = $repoFresh->findOneByFileNameOrHistory('1.jpg');
        self::assertNotNull($viaHistory);
        self::assertSame('1.jpg', $viaHistory->getFileName());
    }

    public function testDoctrineListenerBumpsVersionOnWrite(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $cache = self::getContainer()->get('cache.app');
        $repo = $em->getRepository(Media::class);

        // Read version before any write.
        $before = $cache->getItem(MediaRepository::VERSION_CACHE_KEY);
        $versionBefore = $before->isHit() ? $before->get() : 0;
        $versionBefore = \is_int($versionBefore) ? $versionBefore : 0;

        // Warm the in-process index so we can later verify it was reset.
        self::assertNotNull($repo->findOneByFileName('1.jpg'));
        self::assertTrue($repo->isWarmedLight());

        // Create a physical file so MediaHashListener can compute its sha1.
        $testFileName = '_cache-invalidation-test.jpg';
        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');
        $testFilePath = \rtrim($mediaDir, '/').'/'.$testFileName;
        if (! file_exists($testFilePath)) {
            file_put_contents($testFilePath, 'test');
        }

        // Write a new Media via Doctrine — postPersist listener fires on flush.
        $media = new Media();
        $media->setProjectDir(self::getContainer()->getParameter('kernel.project_dir'))
            ->setStoreIn($mediaDir)
            ->setFileName($testFileName)
            ->setMimeType('image/jpeg')
            ->setSize(1);
        $em->persist($media);
        $em->flush();

        try {
            $after = $cache->getItem(MediaRepository::VERSION_CACHE_KEY);
            $versionAfter = $after->isHit() ? $after->get() : 0;
            $versionAfter = \is_int($versionAfter) ? $versionAfter : 0;
            self::assertGreaterThan($versionBefore, $versionAfter, 'postPersist must bump pw.media.version');

            // In-process light index must have been reset by the listener.
            self::assertFalse($repo->isWarmedLight(), 'Listener must reset in-process index after persist');

            // Next lookup rebuilds and sees the newly persisted media.
            self::assertNotNull($repo->findOneByFileName($testFileName));
        } finally {
            $em->remove($media);
            $em->flush();
            if (file_exists($testFilePath)) {
                unlink($testFilePath);
            }
        }
    }

    public function testBumpVersionInvalidatesPersistentCache(): void
    {
        self::bootKernel();
        $registry = self::getContainer()->get(ManagerRegistry::class);
        $cache = new ArrayAdapter();

        $repoA = new MediaRepository($registry, $cache, debug: false);
        self::assertNotNull($repoA->findOneByFileName('1.jpg'));

        $readVersion = static function () use ($cache): int {
            $value = $cache->getItem(MediaRepository::VERSION_CACHE_KEY)->get();

            return \is_int($value) ? $value : 0;
        };

        $versionBefore = $readVersion();
        $repoA->bumpVersion();
        $versionAfter = $readVersion();
        self::assertSame($versionBefore + 1, $versionAfter);

        // New repo sees the bumped version → cache miss on the old key → rebuilds.
        $repoB = new MediaRepository($registry, $cache, debug: false);
        self::assertNotNull($repoB->findOneByFileName('1.jpg'));
        self::assertTrue($cache->hasItem(MediaRepository::INDEX_CACHE_KEY_PREFIX.$versionAfter));
    }

    private function getMediaRepository(): MediaRepository
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $em->getRepository(Media::class);
    }

    public function getMediaToTestDuplicate(): Media
    {
        return new Media()->setProjectDir(self::getContainer()->getParameter('kernel.project_dir'))
            ->setStoreIn(self::getContainer()->getParameter('pw.media_dir'))
            ->setMimeType('image/jpeg')
            ->setSize(2)
            ->setDimensions([1000, 1000])
            ->setFileName('1.jpg')
            ->setAlt('Demo 1')
            ->setHash();
    }
}
