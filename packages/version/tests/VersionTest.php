<?php

namespace Pushword\Version\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\RevisionCalculator;
use Pushword\Snippet\Entity\Snippet;
use Pushword\Version\Versionner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
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
}
