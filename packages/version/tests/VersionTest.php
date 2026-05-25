<?php

namespace Pushword\Version\Tests;

use Exception;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Snippet\Entity\Snippet;
use Pushword\Version\Versionner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

#[Group('integration')]
final class VersionTest extends KernelTestCase
{
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

        /** @var string $logDir */
        $logDir = $container->getParameter('kernel.logs_dir');
        $versionner = new Versionner(
            $logDir,
            $em,
            new Serializer([], ['json' => new JsonEncoder()])
        );

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

        /** @var string $logDir */
        $logDir = $container->getParameter('kernel.logs_dir');
        $versionner = new Versionner(
            $logDir,
            $em,
            $container->get('serializer')
        );

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

        /** @var string $logDir */
        $logDir = $container->getParameter('kernel.logs_dir');
        $versionner = new Versionner(
            $logDir,
            $container->get('doctrine.orm.default_entity_manager'),
            new Serializer([], ['json' => new JsonEncoder()])
        );

        // Use an id that will never have a version directory on disk.
        self::assertSame([], $versionner->getVersions('page', \PHP_INT_MAX));
        self::assertSame([], $versionner->getVersions('snippet', \PHP_INT_MAX));
    }

    public function testFindThrowsOnUnknownType(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var string $logDir */
        $logDir = $container->getParameter('kernel.logs_dir');
        $versionner = new Versionner(
            $logDir,
            $container->get('doctrine.orm.default_entity_manager'),
            new Serializer([], ['json' => new JsonEncoder()])
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Unknown version type/');
        $versionner->find('not_a_real_type', 1);
    }
}
