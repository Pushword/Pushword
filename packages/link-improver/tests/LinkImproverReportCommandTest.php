<?php

namespace Pushword\LinkImprover\Tests;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class LinkImproverReportCommandTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    protected function tearDown(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $stalePages = $entityManager->getRepository(Page::class)->createQueryBuilder('p')
            ->where("p.slug LIKE 'linkimp-%'")->getQuery()->getResult();
        foreach ($stalePages as $stalePage) {
            $entityManager->remove($stalePage);
        }

        $entityManager->flush();

        parent::tearDown();
    }

    /** Long enough for the default ratio cap (one link per 50 words) to allow one link. */
    private function filler(): string
    {
        return str_repeat('Filler words to raise the word count of this page well over the ratio cap. ', 10);
    }

    private function createPage(string $slug, string $name, string $mainContent, string $host = self::HOST): void
    {
        $page = new Page();
        $page->host = $host;
        $page->slug = $slug;
        $page->name = $name;
        $page->h1 = 'Page '.$slug;
        $page->locale = 'en';
        $page->publishedAt = new DateTime('-1 hour');
        $page->mainContent = $mainContent;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($page);
        $entityManager->flush();
    }

    private function commandTester(): CommandTester
    {
        \assert(null !== self::$kernel);

        return new CommandTester(new Application(self::$kernel)->find('pw:link-improver'));
    }

    public function testAgentReportListsInsertedLinks(): void
    {
        self::bootKernel();
        $this->createPage('linkimp-kiwano', 'Kiwano Melano', 'The target page.');
        $this->createPage('linkimp-source', 'Source', $this->filler().'A page mentioning Kiwano Melano once.');
        self::getContainer()->get(SiteRegistry::class)->get(self::HOST)->setCustomProperty('link_improver', true);

        $commandTester = $this->commandTester();
        $exitCode = $commandTester->execute(['--host' => self::HOST, '--format' => 'agent']);

        self::assertSame(0, $exitCode);
        $report = json_decode($commandTester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        self::assertSame('pw:link-improver', $report['tool']);
        $links = $report['links'] ?? null;
        self::assertIsArray($links);
        self::assertContains(
            ['host' => self::HOST, 'page' => '/linkimp-source', 'anchor' => 'Kiwano Melano', 'url' => '/linkimp-kiwano'],
            $links
        );
    }

    public function testSimulatePreviewsADisabledHost(): void
    {
        self::bootKernel();
        $this->createPage('linkimp-kiwano', 'Kiwano Melano', 'The target page.');
        $this->createPage('linkimp-source', 'Source', $this->filler().'A page mentioning Kiwano Melano once.');

        $commandTester = $this->commandTester();
        $exitCode = $commandTester->execute(['--host' => self::HOST, '--simulate' => true, '--format' => 'text']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('+ "Kiwano Melano" → /linkimp-kiwano', $commandTester->getDisplay());
    }

    public function testWithoutHostEveryEnabledAppIsReported(): void
    {
        $otherHost = 'admin-block-editor.test';

        self::bootKernel();
        $siteRegistry = self::getContainer()->get(SiteRegistry::class);
        // The same slugs on both hosts, as a multisite really holds them:
        // `page` is unique on (slug, host).
        foreach ([self::HOST, $otherHost] as $host) {
            $siteRegistry->get($host)->setCustomProperty('link_improver', true);
            $this->createPage('linkimp-kiwano', 'Kiwano Melano', 'The target page.', $host);
            $this->createPage('linkimp-source', 'Source', $this->filler().'A page mentioning Kiwano Melano once.', $host);
        }

        $commandTester = $this->commandTester();
        $exitCode = $commandTester->execute(['--format' => 'agent']);

        self::assertSame(0, $exitCode);
        $report = json_decode($commandTester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        $links = $report['links'] ?? null;
        self::assertIsArray($links);

        $hosts = array_column($links, 'host');
        self::assertContains(self::HOST, $hosts);
        self::assertContains($otherHost, $hosts);
    }

    public function testADisabledHostIsSkippedWithANote(): void
    {
        self::bootKernel();

        $commandTester = $this->commandTester();
        $exitCode = $commandTester->execute(['--host' => self::HOST, '--format' => 'text']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('link_improver is not enabled', $commandTester->getDisplay());
        self::assertStringContainsString('0 page(s) rendered', $commandTester->getDisplay());
    }
}
