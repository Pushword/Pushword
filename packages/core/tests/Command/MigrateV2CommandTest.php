<?php

namespace Pushword\Core\Tests\Command;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

#[Group('integration')]
final class MigrateV2CommandTest extends KernelTestCase
{
    /** @var int[] page IDs to clean up after each test */
    private array $createdPageIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createdPageIds = [];
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    public function testPromotesTemplateCustomPropertyToItsColumn(): void
    {
        self::bootKernel();
        $pageId = $this->createPage('migrate-v2-template', ['template' => 'custom/page.html.twig']);

        $commandTester = $this->runMigrateCommand();

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Promoted template for page "migrate-v2-template"', $output);
        self::assertStringContainsString('Migrated', $output);
        self::assertSame(0, $commandTester->getStatusCode());

        $em = $this->getEntityManager();
        $em->clear();

        $page = $em->find(Page::class, $pageId);
        self::assertNotNull($page);
        self::assertSame('custom/page.html.twig', $page->getTemplate());
        self::assertFalse($page->hasCustomProperty('template'), 'The custom property is dropped once promoted');
    }

    public function testFixesTheSearchExcerptTypo(): void
    {
        self::bootKernel();
        $pageId = $this->createPage('migrate-v2-excerpt', ['searchExcrept' => 'An excerpt']);

        $commandTester = $this->runMigrateCommand();

        self::assertStringContainsString('Fixed searchExcerpt typo for page "migrate-v2-excerpt"', $commandTester->getDisplay());
        self::assertSame(0, $commandTester->getStatusCode());

        $em = $this->getEntityManager();
        $em->clear();

        $page = $em->find(Page::class, $pageId);
        self::assertNotNull($page);
        self::assertFalse($page->hasCustomProperty('searchExcrept'));
        self::assertSame('An excerpt', $page->getCustomProperty('searchExcerpt'));
    }

    public function testMigratesNothingWhenPagesAreUpToDate(): void
    {
        self::bootKernel();

        $commandTester = $this->runMigrateCommand();

        self::assertStringContainsString('Migrated 0 pages.', $commandTester->getDisplay());
        self::assertSame(0, $commandTester->getStatusCode());
    }

    private function runMigrateCommand(): CommandTester
    {
        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $commandTester = new CommandTester($application->find('pw:migrate'));
        $commandTester->execute([]);

        return $commandTester;
    }

    private function getEntityManager(): EntityManager
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $em;
    }

    /** @param array<string, mixed> $customProperties */
    private function createPage(string $slug, array $customProperties): int
    {
        $em = $this->getEntityManager();

        $page = new Page();
        $page->h1 = 'Migrate V2 Test';
        $page->slug = $slug;
        $page->locale = 'en';
        $page->setMainContent('Page for the v2 migration command.');

        foreach ($customProperties as $name => $value) {
            $page->setCustomProperty($name, $value);
        }

        $em->persist($page);
        $em->flush();

        $this->createdPageIds[] = (int) $page->id;

        return (int) $page->id;
    }

    private function cleanupTestData(): void
    {
        try {
            $em = $this->getEntityManager();
            if (! $em->isOpen()) {
                return;
            }

            $em->clear();

            foreach ($this->createdPageIds as $pageId) {
                $page = $em->find(Page::class, $pageId);
                if (null !== $page) {
                    $em->remove($page);
                }
            }

            $em->flush();
        } catch (Throwable) {
        }
    }
}
