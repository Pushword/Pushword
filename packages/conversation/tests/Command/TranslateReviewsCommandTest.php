<?php

namespace Pushword\Conversation\Tests\Command;

use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Entity\Review;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

#[Group('integration')]
final class TranslateReviewsCommandTest extends KernelTestCase
{
    /** @var int[] */
    private array $createdReviewIds = [];

    #[Override]
    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    public function testRequiresLocaleOption(): void
    {
        self::bootKernel();

        $commandTester = $this->runCommand([]);

        self::assertStringContainsString('You must specify at least one target locale', $commandTester->getDisplay());
        self::assertSame(1, $commandTester->getStatusCode());
    }

    public function testSingleHostShowsHostInOutput(): void
    {
        self::bootKernel();

        $commandTester = $this->runCommand(['--locale' => 'fr', '--host' => 'localhost.dev']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('[localhost.dev]', $output);
        self::assertStringNotContainsString('[pushword.piedweb.com]', $output);
    }

    public function testAllHostsProcessedWhenNoHostOption(): void
    {
        self::bootKernel();

        $commandTester = $this->runCommand(['--locale' => 'fr']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('[localhost.dev]', $output);
        self::assertStringContainsString('[pushword.piedweb.com]', $output);
    }

    /** @param array<string, mixed> $options */
    private function runCommand(array $options): CommandTester
    {
        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $commandTester = new CommandTester($application->find('pw:conversation:translate-reviews'));
        $commandTester->execute($options);

        return $commandTester;
    }

    private function getEntityManager(): EntityManager
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $em;
    }

    private function cleanupTestData(): void
    {
        try {
            $em = $this->getEntityManager();
            if (! $em->isOpen()) {
                return;
            }

            $em->clear();

            foreach ($this->createdReviewIds as $id) {
                $review = $em->find(Review::class, $id);
                if (null !== $review) {
                    $em->remove($review);
                }
            }

            $em->flush();
        } catch (Throwable) {
        }
    }
}
