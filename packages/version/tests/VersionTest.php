<?php

namespace Pushword\Version\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\Core\Service\RevisionCalculator;
use Pushword\Snippet\Entity\Snippet;
use Pushword\Version\Entity\VersionLog;
use Pushword\Version\Versionner;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

#[Group('integration')]
final class VersionTest extends KernelTestCase
{
    private function buildVersionner(string $storageDir, EntityManagerInterface $em, SerializerInterface $serializer): Versionner
    {
        return new Versionner($storageDir, $em, $serializer, new RevisionCalculator($serializer));
    }

    private function buildPlainSerializer(): Serializer
    {
        return new Serializer([new DateTimeNormalizer(), new ObjectNormalizer()], ['json' => new JsonEncoder()]);
    }

    public function testIt(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');

        $repo = $em->getRepository(Page::class);

        // Find any page to test versioning
        $page = $repo->findOneBy(['slug' => 'homepage', 'host' => 'localhost.dev'])
            ?? $repo->findOneBy(['slug' => 'homepage'])
            ?? $repo->findOneBy([]);
        self::assertNotNull($page, 'At least one page should exist');

        $page->setH1('edited title to test Versioning');

        $em->flush();
        $page->setH1('edited title to test Versioning the second time');
        $em->flush();

        /** @var string $storageDir */
        $storageDir = $container->getParameter('pw.pushword_version.storage_dir');
        $versionner = $this->buildVersionner($storageDir, $em, $this->buildPlainSerializer());

        $pageVersions = $versionner->getVersions('page', (int) $page->id);

        self::assertGreaterThanOrEqual(1, \count($pageVersions));
    }

    public function testSnippetIsVersionnedAndRestorable(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');

        $snippet = new Snippet();
        $snippet->host = 'localhost.dev';
        $snippet->setSlug('version-test-'.uniqid());
        $snippet->setName('placeholder');
        $snippet->setContent('placeholder');

        $em->persist($snippet);
        $em->flush(); // assigns the id

        /** @var string $storageDir */
        $storageDir = $container->getParameter('pw.pushword_version.storage_dir');
        $versionner = $this->buildVersionner($storageDir, $em, $container->get('serializer'));

        // Parallel workers have separate DBs but share kernel.logs_dir, so the same
        // auto-increment id can collide on disk. Wipe the dir, then create exactly
        // the two versions this test relies on.
        $versionner->reset('snippet', (int) $snippet->id);

        $snippet->setName('Original name');
        $snippet->setContent('original content');

        $em->flush(); // version 1 (the state we will restore to)

        $snippet->setName('Renamed');
        $snippet->setContent('changed content');

        $em->flush(); // version 2

        $versions = $versionner->getVersions('snippet', (int) $snippet->id);
        self::assertCount(2, $versions, 'The two updates should each create exactly one version');

        // Restore the first version and confirm the original values came back.
        $versionner->loadVersion('snippet', (string) $snippet->id, $versions[0]);

        $restored = $em->getRepository(Snippet::class)->find($snippet->id);
        self::assertNotNull($restored);
        self::assertSame('Original name', $restored->getName());
        self::assertSame('original content', $restored->getContent());

        $versionner->reset('snippet', (int) $snippet->id);
        $em->remove($restored);
        $em->flush();
    }

    /**
     * Two postUpdate dispatches on the same column-mapped state must produce a
     * single version file. Guards the hash-suffix duplicate-save short-circuit
     * in Versionner::createVersion.
     */
    public function testIdempotentSaveDoesNotCreateDuplicateVersion(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');

        $snippet = new Snippet();
        $snippet->host = 'localhost.dev';
        $snippet->setSlug('idempotent-version-'.uniqid());
        $snippet->setName('Stable name');
        $snippet->setContent('Stable content');

        $em->persist($snippet);
        $em->flush(); // assigns the id

        /** @var string $storageDir */
        $storageDir = $container->getParameter('pw.pushword_version.storage_dir');
        $versionner = $this->buildVersionner($storageDir, $em, $container->get('serializer'));

        $id = (int) $snippet->id;

        // Parallel workers share kernel.logs_dir but have separate DBs, so stale
        // version files may already exist under this id. Wipe before asserting.
        $versionner->reset('snippet', $id);

        $versionner->postUpdate(new PostUpdateEventArgs($snippet, $em));
        self::assertCount(1, $versionner->getVersions('snippet', $id), 'First save must create exactly one version');

        // Re-dispatching postUpdate with identical column state must be a no-op.
        $versionner->postUpdate(new PostUpdateEventArgs($snippet, $em));
        self::assertCount(1, $versionner->getVersions('snippet', $id), 'Identical state must not produce a second version file');

        // The stored filename embeds the content hash as suffix.
        [$only] = $versionner->getVersions('snippet', $id);
        self::assertStringContainsString('_', $only, 'Filename must follow the <prefix>_<hash> convention');

        $versionner->reset('snippet', $id);
        $em->remove($snippet);
        $em->flush();
    }

    /**
     * editedBy is a ManyToOne association, absent from the hash-backed snapshot.
     * Versionner injects it for the version list, and populate() must ignore it
     * on restore so no phantom User is built.
     */
    public function testEditorIsCapturedInSnapshotButIgnoredOnRestore(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');

        $user = $em->getRepository(User::class)->findOneBy([]);
        self::assertNotNull($user, 'A user is required to act as editor');

        $page = $em->getRepository(Page::class)->findOneBy(['slug' => 'homepage'])
            ?? $em->getRepository(Page::class)->findOneBy([]);
        self::assertNotNull($page);

        $page->editedBy = $user;
        $page->setH1('editor capture test '.uniqid());

        $em->flush();

        /** @var string $storageDir */
        $storageDir = $container->getParameter('pw.pushword_version.storage_dir');
        $versionner = $this->buildVersionner($storageDir, $em, $container->get('serializer'));

        $latest = $versionner->getLatestVersion('page', (int) $page->id);
        self::assertNotNull($latest);
        $snapshot = new Filesystem()->readFile($storageDir.'/'.(int) $page->id.'/'.$latest);
        self::assertStringContainsString('"editedBy"', $snapshot, 'Snapshot must record the editor');
        self::assertStringContainsString($user->getUserIdentifier(), $snapshot);

        // The version list reads the editor through editorOf(), not the entity.
        self::assertSame($user->getUserIdentifier(), $versionner->editorOf('page', (int) $page->id, $latest));

        // Restore must not fail nor replace the editor with a phantom User.
        $versionner->loadVersion('page', (string) $page->id, $latest);
        $restored = $em->getRepository(Page::class)->find($page->id);
        self::assertNotNull($restored);
        self::assertSame($user->id, $restored->editedBy?->id);
    }

    public function testVersionableTypesIncludesSnippetWhenInstalled(): void
    {
        $types = Versionner::versionableTypes();

        self::assertArrayHasKey('page', $types);
        self::assertSame(Page::class, $types['page']);
        self::assertArrayHasKey('snippet', $types, 'pushword/snippet is installed in the monorepo, so snippet must be versionable');
        self::assertSame(Snippet::class, $types['snippet']);
    }

    public function testGetVersionsReturnsEmptyArrayWhenNoVersionFilesExist(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var string $storageDir */
        $storageDir = $container->getParameter('pw.pushword_version.storage_dir');
        $versionner = $this->buildVersionner(
            $storageDir,
            $container->get('doctrine.orm.default_entity_manager'),
            $this->buildPlainSerializer(),
        );

        // Use an id that will never have a version directory on disk.
        self::assertSame([], $versionner->getVersions('page', \PHP_INT_MAX));
        self::assertSame([], $versionner->getVersions('snippet', \PHP_INT_MAX));
    }

    public function testRetentionPrunesOldVersionsInTiers(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');

        /** @var string $storageDir */
        $storageDir = $container->getParameter('pw.pushword_version.storage_dir');
        $versionner = $this->buildVersionner($storageDir, $em, $container->get('serializer'));

        $snippet = new Snippet();
        $snippet->host = 'localhost.dev';
        $snippet->setSlug('retention-test-'.uniqid());
        $snippet->setName('placeholder');
        $snippet->setContent('placeholder');

        $em->persist($snippet);
        $em->flush(); // assigns the id (and creates a recent version)

        $id = (int) $snippet->id;
        $versionner->reset('snippet', $id);

        $now = time();
        $dir = $storageDir.'/snippet/'.$id;
        $fs = new Filesystem();
        // Version filenames start with an 8-hex-char Unix timestamp.
        $name = static fn (int $ts, string $suffix): string => str_pad(dechex($ts), 8, '0', \STR_PAD_LEFT).$suffix;

        // Bucket-align the old timestamps so each pair lands in a single bucket.
        $day = intdiv($now - 45 * 86400, 86400) * 86400;        // 30–90 days window
        $week = intdiv($now - 200 * 86400, 7 * 86400) * 7 * 86400; // beyond 90 days

        // Recent (< 30 days): both kept.
        $fs->dumpFile($dir.'/'.$name($now - 86400, '00001'), '{}');
        $fs->dumpFile($dir.'/'.$name($now - 2 * 86400, '00002'), '{}');
        // Same calendar day: collapsed to the newer one.
        $fs->dumpFile($dir.'/'.$name($day + 10, '00001'), '{}');
        $fs->dumpFile($dir.'/'.$name($day + 20, '00002'), '{}');
        // Same 7-day bucket: collapsed to the newer one.
        $fs->dumpFile($dir.'/'.$name($week + 10, '00001'), '{}');
        $fs->dumpFile($dir.'/'.$name($week + 20, '00002'), '{}');

        self::assertCount(6, $versionner->getVersions('snippet', $id));

        // A new save triggers pruning.
        $snippet->setContent('changed');
        $em->flush();

        $versions = $versionner->getVersions('snippet', $id);
        // 2 recent + 1 new save + 1 day survivor + 1 week survivor.
        self::assertCount(5, $versions);
        self::assertContains($name($day + 20, '00002'), $versions);
        self::assertNotContains($name($day + 10, '00001'), $versions);
        self::assertContains($name($week + 20, '00002'), $versions);
        self::assertNotContains($name($week + 10, '00001'), $versions);

        $versionner->reset('snippet', $id);
        $em->remove($snippet);
        $em->flush();
    }

    public function testGetLatestVersionReturnsNewestOrNull(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var string $storageDir */
        $storageDir = $container->getParameter('pw.pushword_version.storage_dir');
        $versionner = $this->buildVersionner(
            $storageDir,
            $container->get('doctrine.orm.default_entity_manager'),
            $this->buildPlainSerializer(),
        );

        // No version directory on disk → null.
        self::assertNull($versionner->getLatestVersion('page', \PHP_INT_MAX));

        // Write out-of-order version files; the chronologically greatest wins.
        $dir = $storageDir.'/snippet/'.\PHP_INT_MAX;
        $fs = new Filesystem();
        $fs->dumpFile($dir.'/64000000aaa', '{}');
        $fs->dumpFile($dir.'/65000000ccc', '{}'); // newest
        $fs->dumpFile($dir.'/64ffffffbbb', '{}');

        self::assertSame('65000000ccc', $versionner->getLatestVersion('snippet', \PHP_INT_MAX));

        $versionner->reset('snippet', \PHP_INT_MAX);
    }

    /**
     * A page kept on hold records `holdPublicationAt` in every snapshot taken
     * while held, which is why the version list shows "on hold" on all of them.
     * The page-list shortcut therefore diffs against the newest snapshot that was
     * *not* held (the last state that reached production), so the review shows
     * exactly what the hold is keeping back — skipping the more recent held ones.
     */
    public function testPickComparisonVersionForHeldPageTargetsLastLiveVersion(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');
        $versionner = $container->get(Versionner::class);

        $page = $this->createFreshPage($em, 'A');
        $id = (int) $page->id;
        $versionner->reset('page', $id); // drop the creation snapshot for a clean slate

        $page->setMainContent('B');
        $em->flush(); // v1 — live
        $page->setMainContent('C');
        $em->flush(); // v2 — live (the newest not-held state)

        $page->setHoldPublication(true);
        $page->setMainContent('D');

        $em->flush(); // v3 — held

        $versions = $versionner->getVersions('page', $id);
        sort($versions);
        self::assertCount(3, $versions);

        // Held snapshots carry the flag, live ones don't (this is what paints the
        // whole list "on hold" once a page stays held).
        self::assertSame($versions[1], $versionner->pickComparisonVersion('page', $id), 'newest not-held snapshot is the diff baseline');

        $versionner->reset('page', $id);
        $em->remove($page);
        $em->flush();
    }

    /**
     * A live (not-held) page diffs against the previous distinct revision. The
     * latest snapshot mirrors the current state, so it is skipped.
     */
    public function testPickComparisonVersionForLivePageTargetsPreviousRevision(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');
        $versionner = $container->get(Versionner::class);

        $page = $this->createFreshPage($em, 'A');
        $id = (int) $page->id;
        $versionner->reset('page', $id);

        $page->setMainContent('B');
        $em->flush(); // v1
        $page->setMainContent('C');
        $em->flush(); // v2 — mirrors current

        $versions = $versionner->getVersions('page', $id);
        sort($versions);
        self::assertCount(2, $versions);

        self::assertSame($versions[0], $versionner->pickComparisonVersion('page', $id), 'v2 mirrors current, so the previous revision (v1) is the baseline');

        $versionner->reset('page', $id);
        $em->remove($page);
        $em->flush();
    }

    /**
     * When every snapshot was itself held (the page went on hold before its first
     * recorded version), fall back to the oldest version.
     */
    public function testPickComparisonVersionAllHeldFallsBackToOldest(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');
        $versionner = $container->get(Versionner::class);

        $page = $this->createFreshPage($em, 'A');
        $id = (int) $page->id;
        $versionner->reset('page', $id);

        $page->setHoldPublication(true);
        $page->setMainContent('B');

        $em->flush(); // v1 — held
        $page->setMainContent('C');
        $em->flush(); // v2 — held

        $versions = $versionner->getVersions('page', $id);
        sort($versions);
        self::assertCount(2, $versions);

        self::assertSame($versions[0], $versionner->pickComparisonVersion('page', $id), 'no live snapshot exists, so the oldest is the baseline');

        $versionner->reset('page', $id);
        $em->remove($page);
        $em->flush();
    }

    public function testPickComparisonVersionReturnsNullWithoutHistory(): void
    {
        self::bootKernel();
        $versionner = self::getContainer()->get(Versionner::class);

        self::assertNull($versionner->pickComparisonVersion('page', \PHP_INT_MAX));
    }

    private function createFreshPage(EntityManagerInterface $em, string $mainContent): Page
    {
        $page = new Page();
        $page->host = 'version-pick-'.uniqid().'.example.com';
        $page->setSlug('pick-'.uniqid());
        $page->setMainContent($mainContent);

        $em->persist($page);
        $em->flush(); // assigns the id (and creates a first snapshot)

        return $page;
    }

    public function testFindThrowsOnUnknownType(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var string $storageDir */
        $storageDir = $container->getParameter('pw.pushword_version.storage_dir');
        $versionner = $this->buildVersionner(
            $storageDir,
            $container->get('doctrine.orm.default_entity_manager'),
            $this->buildPlainSerializer(),
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Unknown version type/');
        $versionner->find('not_a_real_type', 1);
    }

    /**
     * Each versionable action appends one row to the queryable version_log
     * index, tagged with its action (created/updated) and denormalized
     * title/host, ordered newest first — the data the admin activity journal
     * reads without reopening snapshot files.
     */
    public function testActivityIsLoggedPerActionNewestFirst(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');
        $repo = $em->getRepository(VersionLog::class);

        $slug = 'activity-log-'.uniqid();
        $snippet = new Snippet();
        $snippet->host = 'localhost.dev';
        $snippet->setSlug($slug);
        $snippet->setName('First name');
        $snippet->setContent('Body');

        $em->persist($snippet);
        $em->flush();
        // postPersist => "created"
        $id = (int) $snippet->id;

        $created = $repo->findBy(['type' => 'snippet', 'entityId' => $id]);
        self::assertCount(1, $created, 'Creating an entity logs exactly one activity row');
        self::assertSame(VersionLog::ACTION_CREATED, $created[0]->action);
        self::assertSame('First name', $created[0]->title);
        self::assertSame($slug, $created[0]->slug, 'Slug is denormalized for the journal second line');
        self::assertSame('localhost.dev', $created[0]->host);

        $snippet->setName('Second name');
        $em->flush(); // postUpdate => "updated"

        // Newest first: createdAt may tie within the same second, so id breaks it.
        $rows = $repo->findBy(['type' => 'snippet', 'entityId' => $id], ['createdAt' => 'DESC', 'id' => 'DESC']);
        self::assertCount(2, $rows, 'Updating logs a second activity row');
        self::assertSame(VersionLog::ACTION_UPDATED, $rows[0]->action, 'Most recent row first');
        self::assertSame('Second name', $rows[0]->title, 'Title is denormalized at action time');

        // Re-flushing with no column change must not log a duplicate (idempotency).
        $em->flush();
        self::assertCount(2, $repo->findBy(['type' => 'snippet', 'entityId' => $id]), 'Unchanged state logs nothing');

        // A restore is logged explicitly (the VersionController path) as a
        // "restored" action, carrying the acting user that find() can't supply.
        $container->get(Versionner::class)->logActivity('snippet', $snippet, $rows[0]->version, VersionLog::ACTION_RESTORED, 'admin@example.tld');
        $restored = $repo->findOneBy(['type' => 'snippet', 'entityId' => $id, 'action' => VersionLog::ACTION_RESTORED]);
        self::assertNotNull($restored, 'Restoring logs a "restored" row');
        self::assertSame('admin@example.tld', $restored->editor, 'The restorer is recorded');

        $container->get(Versionner::class)->reset('snippet', $id);
        foreach ($repo->findBy(['type' => 'snippet', 'entityId' => $id]) as $row) {
            $em->remove($row);
        }

        $em->remove($snippet);
        $em->flush();
    }

    /**
     * pw:version:log:clear purges the journal: --days keeps recent rows, a bare
     * run wipes everything after confirmation. Snapshots on disk are untouched.
     */
    public function testClearLogCommandPurgesEntries(): void
    {
        $kernel = self::bootKernel();

        $container = self::getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');
        $repo = $em->getRepository(VersionLog::class);

        $snippet = new Snippet();
        $snippet->host = 'localhost.dev';
        $snippet->setSlug('clear-log-'.uniqid());
        $snippet->setName('Clear me');
        $snippet->setContent('Body');

        $em->persist($snippet);
        $em->flush(); // logs one "created" row

        self::assertGreaterThanOrEqual(1, \count($repo->findAll()));

        $application = new Application($kernel);
        $tester = new CommandTester($application->find('pw:version:log:clear'));

        // Nothing is old enough to prune, so the journal is untouched.
        $tester->execute(['--days' => 36500]);
        $tester->assertCommandIsSuccessful();
        self::assertGreaterThanOrEqual(1, \count($repo->findAll()), 'No row is 100 years old');

        // A bare run wipes everything once confirmed.
        $tester->setInputs(['yes']);
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $em->clear();
        self::assertSame([], $repo->findAll(), 'Full clear empties the journal');

        $container->get(Versionner::class)->reset('snippet', (int) $snippet->id);
        $deletable = $em->getRepository(Snippet::class)->find($snippet->id);
        if (null !== $deletable) {
            $em->remove($deletable);
            $em->flush();
        }
    }
}
