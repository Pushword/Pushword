<?php

namespace Pushword\Core\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class DeletePageCommandTest extends KernelTestCase
{
    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('pw:page:delete'));
    }

    private function createPage(string $slug, string $tags): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $page = new Page();
        $page->slug = $slug;
        $page->h1 = $slug;
        $page->locale = 'en';
        $page->setTags($tags);

        $em->persist($page);
        $em->flush();
    }

    public function testItDeletesOnlyThePagesCarryingTheTag(): void
    {
        $commandTester = $this->tester();
        $this->createPage('delete-cmd-demo-one', 'demoRemoval');
        $this->createPage('delete-cmd-demo-two', 'demoRemoval');
        $this->createPage('delete-cmd-keeper', 'somethingElse');

        $commandTester->execute(['--tag' => 'demoRemoval', '--force' => true, '--format' => 'text']);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('Deleted 2 page(s)', $commandTester->getDisplay());

        $pageRepo = self::getContainer()->get(PageRepository::class);
        self::assertNull($pageRepo->findOneBy(['slug' => 'delete-cmd-demo-one']));
        self::assertNull($pageRepo->findOneBy(['slug' => 'delete-cmd-demo-two']));
        self::assertNotNull($pageRepo->findOneBy(['slug' => 'delete-cmd-keeper']));
    }

    public function testItRefusesToDeleteWithoutForceWhenNoTerminalIsAttached(): void
    {
        $commandTester = $this->tester();
        $this->createPage('delete-cmd-untouched', 'demoKept');

        $commandTester->execute(['--tag' => 'demoKept', '--format' => 'text'], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertStringContainsString('--force', $commandTester->getDisplay());

        $pageRepo = self::getContainer()->get(PageRepository::class);
        self::assertNotNull($pageRepo->findOneBy(['slug' => 'delete-cmd-untouched']));
    }

    public function testAnsweringNoToTheConfirmationDeletesNothing(): void
    {
        $commandTester = $this->tester();
        $this->createPage('delete-cmd-declined', 'demoDeclined');

        $commandTester->setInputs(['no']);
        $commandTester->execute(['--tag' => 'demoDeclined', '--format' => 'text']);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('delete-cmd-declined', $commandTester->getDisplay());

        $pageRepo = self::getContainer()->get(PageRepository::class);
        self::assertNotNull($pageRepo->findOneBy(['slug' => 'delete-cmd-declined']));
    }

    public function testAgentFormatReportsWhyItRefusedWithoutForce(): void
    {
        $commandTester = $this->tester();
        $this->createPage('delete-cmd-blocked', 'demoBlocked');

        $commandTester->execute(['--tag' => 'demoBlocked', '--format' => 'agent'], ['interactive' => false]);

        $json = json_decode(trim($commandTester->getDisplay()), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame([
            'tool' => 'pw:page:delete',
            'result' => 'blocked',
            'tag' => 'demoBlocked',
            'matched' => 1,
            'slugs' => ['delete-cmd-blocked'],
            'error' => 'pass --force to delete without a terminal',
        ], $json);
    }

    public function testAgentFormatReportsWhatItDeleted(): void
    {
        $commandTester = $this->tester();
        $this->createPage('delete-cmd-agent', 'demoAgent');

        $commandTester->execute(['--tag' => 'demoAgent', '--force' => true, '--format' => 'agent']);

        $json = json_decode(trim($commandTester->getDisplay()), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame([
            'tool' => 'pw:page:delete',
            'result' => 'done',
            'tag' => 'demoAgent',
            'deleted' => 1,
            'slugs' => ['delete-cmd-agent'],
        ], $json);
    }

    public function testAnUnknownTagDeletesNothing(): void
    {
        $commandTester = $this->tester();

        $commandTester->execute(['--tag' => 'noPageCarriesThisTag', '--force' => true, '--format' => 'text']);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('No page tagged', $commandTester->getDisplay());
    }

    public function testTheTagIsRequired(): void
    {
        $commandTester = $this->tester();

        $commandTester->execute(['--format' => 'text']);

        self::assertSame(Command::INVALID, $commandTester->getStatusCode());
        self::assertStringContainsString('--tag is required', $commandTester->getDisplay());
    }
}
