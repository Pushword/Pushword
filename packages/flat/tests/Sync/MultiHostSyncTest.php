<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Sync;

use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\FlatFileSync;
use Pushword\Flat\Sync\SyncStateManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class MultiHostSyncTest extends KernelTestCase
{
    private EntityManager $em;

    private FlatFileSync $flatFileSync;

    private FlatFileContentDirFinder $contentDirFinder;

    private SyncStateManager $stateManager;

    /** @var string[] */
    private array $createdFiles = [];

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->em = $em;

        /** @var FlatFileSync $flatFileSync */
        $flatFileSync = self::getContainer()->get(FlatFileSync::class);
        $this->flatFileSync = $flatFileSync;

        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $this->contentDirFinder = $contentDirFinder;

        /** @var SyncStateManager $stateManager */
        $stateManager = self::getContainer()->get(SyncStateManager::class);
        $this->stateManager = $stateManager;
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }

        foreach (['multi-host-test-page'] as $slug) {
            foreach (['localhost.dev', 'pushword.piedweb.com'] as $host) {
                $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => $slug, 'host' => $host]);
                if ($page instanceof Page) {
                    $this->em->remove($page);
                }
            }
        }

        $this->em->flush();

        parent::tearDown();
    }

    public function testSyncWithoutHostSyncsAllHosts(): void
    {
        $hosts = $this->flatFileSync->getHosts();
        self::assertContains('localhost.dev', $hosts);
        self::assertContains('pushword.piedweb.com', $hosts);
        self::assertGreaterThanOrEqual(2, \count($hosts));
    }

    public function testHostSpecificPagesNotCrossContaminated(): void
    {
        // Create page only on host A
        $page = new Page();
        $page->setSlug('multi-host-test-page');
        $page->setH1('Multi Host Test');
        $page->host = 'localhost.dev';
        $page->locale = 'en';
        $page->setMainContent('Host A only');

        $this->em->persist($page);
        $this->em->flush();

        // Export both hosts
        $contentDirA = $this->contentDirFinder->get('localhost.dev');
        $contentDirB = $this->contentDirFinder->get('pushword.piedweb.com');

        $this->flatFileSync->export('localhost.dev');
        $this->flatFileSync->export('pushword.piedweb.com');

        // Page should exist in host A content dir
        $mdPathA = $contentDirA.'/multi-host-test-page.md';
        self::assertFileExists($mdPathA);
        $this->createdFiles[] = $mdPathA;

        // Page should NOT exist in host B content dir
        $mdPathB = $contentDirB.'/multi-host-test-page.md';
        self::assertFileDoesNotExist($mdPathB, 'Host A page should not appear in host B content directory');
    }

    public function testImportForOneHostDoesNotAffectOther(): void
    {
        // Get pages for host B before import
        $hostBPagesBefore = $this->em->getRepository(Page::class)->findBy(['host' => 'pushword.piedweb.com']);
        $hostBSlugsBefore = array_map(static fn (Page $p): string => $p->getSlug(), $hostBPagesBefore);

        // Export host A and import
        $this->flatFileSync->export('localhost.dev');
        $this->flatFileSync->import('localhost.dev');

        // Host B pages should be unchanged
        $this->em->clear();
        $hostBPagesAfter = $this->em->getRepository(Page::class)->findBy(['host' => 'pushword.piedweb.com']);
        $hostBSlugsAfter = array_map(static fn (Page $p): string => $p->getSlug(), $hostBPagesAfter);

        sort($hostBSlugsBefore);
        sort($hostBSlugsAfter);

        self::assertSame($hostBSlugsBefore, $hostBSlugsAfter, 'Importing host A should not affect host B pages');
    }

    public function testSyncStateTrackedPerHost(): void
    {
        // Reset state
        $this->stateManager->resetState('localhost.dev');
        $this->stateManager->resetState('pushword.piedweb.com');

        // Export host A
        $this->flatFileSync->export('localhost.dev');

        // Host A should have state, host B should not (yet)
        $hostATime = $this->stateManager->getLastSyncTime('page', 'localhost.dev');
        self::assertGreaterThan(0, $hostATime, 'Host A should have sync state after export');

        $hostBTime = $this->stateManager->getLastSyncTime('page', 'pushword.piedweb.com');
        self::assertSame(0, $hostBTime, 'Host B should not have sync state yet');

        // Now export host B
        $this->flatFileSync->export('pushword.piedweb.com');
        $hostBTime = $this->stateManager->getLastSyncTime('page', 'pushword.piedweb.com');
        self::assertGreaterThan(0, $hostBTime, 'Host B should now have sync state');
    }

    public function testMultiHostContentDirsAreDifferent(): void
    {
        $contentDirA = $this->contentDirFinder->get('localhost.dev');
        $contentDirB = $this->contentDirFinder->get('pushword.piedweb.com');

        self::assertNotSame($contentDirA, $contentDirB, 'Content directories for different hosts must be different');
    }
}
