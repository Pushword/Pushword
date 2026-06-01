<?php

namespace Pushword\Core\Tests\Command;

use DateTime;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class RedirectMigrateCommandTest extends KernelTestCase
{
    private EntityManager $em;

    /** @var string[] */
    private array $slugs = ['rfm-destination', 'rfm-old', 'rfm-external'];

    protected function setUp(): void
    {
        self::bootKernel();
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->em = $em;
    }

    protected function tearDown(): void
    {
        $this->em->clear();
        foreach ($this->slugs as $slug) {
            $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => $slug, 'host' => 'localhost.dev']);
            if ($page instanceof Page) {
                $this->em->remove($page);
            }
        }

        $this->em->flush();
        parent::tearDown();
    }

    public function testMigrateFoldsInternalPhantomAndKeepsExternal(): void
    {
        $this->createPage('rfm-destination', 'Destination content');
        $this->createPage('rfm-old', 'Location: /rfm-destination 301');
        $this->createPage('rfm-external', 'Location: https://example.com 301');

        $tester = $this->runMigrate(['host' => 'localhost.dev']);
        self::assertSame(0, $tester->getStatusCode());

        $this->em->clear();

        // Internal phantom folded into the destination's redirectFrom, and deleted.
        $destination = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'rfm-destination', 'host' => 'localhost.dev']);
        self::assertNotNull($destination);
        self::assertSame(['rfm-old' => 301], $destination->getRedirectFromMap());

        self::assertNull($this->em->getRepository(Page::class)->findOneBy(['slug' => 'rfm-old', 'host' => 'localhost.dev']));

        // External phantom is preserved.
        $external = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'rfm-external', 'host' => 'localhost.dev']);
        self::assertNotNull($external);
        self::assertTrue($external->hasRedirection());
    }

    public function testDryRunDoesNotModify(): void
    {
        $this->createPage('rfm-destination', 'Destination content');
        $this->createPage('rfm-old', 'Location: /rfm-destination 301');

        $tester = $this->runMigrate(['host' => 'localhost.dev', '--dry-run' => true]);
        self::assertSame(0, $tester->getStatusCode());

        $this->em->clear();
        $phantom = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'rfm-old', 'host' => 'localhost.dev']);
        self::assertNotNull($phantom, 'Dry run must not delete the phantom page');
        $destination = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'rfm-destination', 'host' => 'localhost.dev']);
        self::assertNotNull($destination);
        self::assertSame([], $destination->getRedirectFromMap());
    }

    private function createPage(string $slug, string $mainContent): void
    {
        $page = new Page();
        $page->setSlug($slug);
        $page->setH1('Test '.$slug);
        $page->host = 'localhost.dev';
        $page->locale = 'en';
        $page->createdAt = new DateTime();
        $page->updatedAt = new DateTime();
        $page->setMainContent($mainContent);

        $this->em->persist($page);
        $this->em->flush();
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runMigrate(array $input): CommandTester
    {
        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $tester = new CommandTester($application->find('pw:redirect:migrate'));
        $tester->execute($input);

        return $tester;
    }
}
