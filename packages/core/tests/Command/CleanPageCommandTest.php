<?php

namespace Pushword\Core\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The command re-runs Page::normalizeMainContent() over stored content, for rows
 * written before a normalization existed. Its body is one loop whose effect is
 * invisible in the return code — hence this test: an empty loop still exits 0.
 */
#[Group('integration')]
final class CleanPageCommandTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private const string SLUG = 'clean-page-fixture';

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $page = $this->entityManager->getRepository(Page::class)
            ->findOneBy(['slug' => self::SLUG, 'host' => self::HOST]);

        if (null !== $page) {
            $this->entityManager->remove($page);
            $this->entityManager->flush();
        }

        parent::tearDown();
    }

    public function testItNormalizesContentStoredWithoutGoingThroughTheSetter(): void
    {
        $page = new Page();
        $page->slug = self::SLUG;
        $page->host = self::HOST;
        $page->h1 = 'Clean me';

        // Write past the hook, the way a row predating the normalization looks.
        new ReflectionProperty(Page::class, 'mainContent')
            ->setRawValue($page, '  Before <a href="#x" class="anchor"></a>after.  ');

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        $application = new Application(self::$kernel ?? throw new LogicException());

        $tester = new CommandTester($application->find('pw:page:clean'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $this->entityManager->clear();
        $cleaned = $this->entityManager->getRepository(Page::class)
            ->findOneBy(['slug' => self::SLUG, 'host' => self::HOST]);

        self::assertNotNull($cleaned);
        self::assertSame('Before after.', $cleaned->mainContent);
    }
}
