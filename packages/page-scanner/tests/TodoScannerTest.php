<?php

namespace Pushword\PageScanner;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\PageScanner\Scanner\TodoScanner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class TodoScannerTest extends KernelTestCase
{
    private TodoScanner $scanner;

    private EntityManagerInterface $entityManager;

    /** @var Page[] */
    private array $createdPages = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->scanner = self::getContainer()->get(TodoScanner::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdPages as $page) {
            $this->entityManager->remove($page);
        }

        if ([] !== $this->createdPages) {
            $this->entityManager->flush();
        }

        $this->createdPages = [];
        parent::tearDown();
    }

    public function testEmptyContent(): void
    {
        $source = $this->buildSourcePage('');
        self::assertCount(0, $this->scanner->scan($source, ''));
    }

    public function testLinkWhenPublishedWithPublishedTarget(): void
    {
        $this->createPage('published-target', 'localhost.dev', new DateTime('-1 day'));
        $source = $this->buildSourcePage('<!--TODO:linkWhenPublished published-target -->');

        $errors = $this->scanner->scan($source, '');

        self::assertCount(1, $errors);
        self::assertStringContainsString('published-target', $errors[0]);
        self::assertStringContainsString('link', $errors[0]);
    }

    public function testLinkWhenPublishedWithUnpublishedTarget(): void
    {
        $this->createPage('unpublished-target', 'localhost.dev', new DateTime('+10 days'));
        $source = $this->buildSourcePage('<!--TODO:linkWhenPublished unpublished-target -->');

        self::assertCount(0, $this->scanner->scan($source, ''));
    }

    public function testLinkWhenPublishedWithUnknownSlug(): void
    {
        $source = $this->buildSourcePage('<!--TODO:linkWhenPublished nonexistent-slug -->');

        $errors = $this->scanner->scan($source, '');

        self::assertCount(1, $errors);
        self::assertStringContainsString('nonexistent-slug', $errors[0]);
    }

    public function testDoWhenPublishedWithPublishedTarget(): void
    {
        $this->createPage('do-target', 'localhost.dev', new DateTime('-1 day'));
        $source = $this->buildSourcePage('<!--TODO:doWhenPublished do-target "add comparison table" -->');

        $errors = $this->scanner->scan($source, '');

        self::assertCount(1, $errors);
        self::assertStringContainsString('do-target', $errors[0]);
        self::assertStringContainsString('add comparison table', $errors[0]);
        self::assertStringNotContainsString('link', $errors[0]);
    }

    public function testDoWhenPublishedWithUnpublishedTarget(): void
    {
        $this->createPage('do-unpublished', 'localhost.dev', new DateTime('+10 days'));
        $source = $this->buildSourcePage('<!--TODO:doWhenPublished do-unpublished "update section" -->');

        self::assertCount(0, $this->scanner->scan($source, ''));
    }

    public function testDoWhenPublishedWithUnknownSlug(): void
    {
        $source = $this->buildSourcePage('<!--TODO:doWhenPublished unknown-do-target "fix this" -->');

        $errors = $this->scanner->scan($source, '');

        self::assertCount(1, $errors);
        self::assertStringContainsString('unknown-do-target', $errors[0]);
    }

    public function testDoWhenPublishedWithoutLabel(): void
    {
        $this->createPage('do-nolabel', 'localhost.dev', new DateTime('-1 day'));
        $source = $this->buildSourcePage('<!--TODO:doWhenPublished do-nolabel -->');

        $errors = $this->scanner->scan($source, '');

        self::assertCount(1, $errors);
        self::assertStringNotContainsString('(', $errors[0]);
    }

    public function testLinkWhenPublishedWithAnchorText(): void
    {
        $this->createPage('anchor-target', 'localhost.dev', new DateTime('-1 day'));
        $source = $this->buildSourcePage('<!--TODO:linkWhenPublished anchor-target "click here" -->');

        $errors = $this->scanner->scan($source, '');

        self::assertCount(1, $errors);
        self::assertStringContainsString('click here', $errors[0]);
    }

    public function testCaseInsensitive(): void
    {
        $this->createPage('case-target', 'localhost.dev', new DateTime('-1 day'));
        $source = $this->buildSourcePage('<!--todo:linkwhenpublished case-target -->');

        self::assertCount(1, $this->scanner->scan($source, ''));
    }

    public function testLinkWhenPublishedWithExplicitHost(): void
    {
        $this->createPage('cross-host-target', 'pushword.piedweb.com', new DateTime('-1 day'));
        $source = $this->buildSourcePage('<!--TODO:linkWhenPublished pushword.piedweb.com/cross-host-target -->');

        $errors = $this->scanner->scan($source, '');

        self::assertCount(1, $errors);
        self::assertStringContainsString('cross-host-target', $errors[0]);
    }

    public function testExplicitHostNotFoundOnDifferentHost(): void
    {
        $this->createPage('host-mismatch', 'localhost.dev', new DateTime('-1 day'));
        $source = $this->buildSourcePage('<!--TODO:linkWhenPublished other-host.com/host-mismatch -->');

        $errors = $this->scanner->scan($source, '');

        self::assertCount(1, $errors);
        self::assertStringContainsString('unknown', strtolower($errors[0]));
    }

    public function testPlainTodoCommentIsIgnored(): void
    {
        $source = $this->buildSourcePage('<!--TODO-->');
        self::assertCount(0, $this->scanner->scan($source, ''));
    }

    public function testUnrecognizedTodoCommentIsIgnored(): void
    {
        $source = $this->buildSourcePage('<!--TODO: fix this later -->');
        self::assertCount(0, $this->scanner->scan($source, ''));
    }

    public function testMultipleTodosInSamePage(): void
    {
        $this->createPage('multi-target', 'localhost.dev', new DateTime('-1 day'));
        $content = "Some text\n<!--TODO:linkWhenPublished multi-target -->\nMore text\n<!--TODO:linkWhenPublished nonexistent-page -->";
        $source = $this->buildSourcePage($content);

        $errors = $this->scanner->scan($source, '');

        self::assertCount(2, $errors);
    }

    private function buildSourcePage(string $mainContent): Page
    {
        $page = new Page();
        $page->setH1('Source page');
        $page->setSlug('source-page');
        $page->host = 'localhost.dev';
        $page->locale = 'en';
        $page->createdAt = new DateTime();
        $page->setMainContent($mainContent);

        return $page;
    }

    private function createPage(string $slug, string $host, DateTime $publishedAt): Page
    {
        $page = new Page();
        $page->setH1('Target: '.$slug);
        $page->setSlug($slug);
        $page->host = $host;
        $page->locale = 'en';
        $page->createdAt = new DateTime();
        $page->publishedAt = $publishedAt;
        $page->setMainContent('Target page content');

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        $this->createdPages[] = $page;

        return $page;
    }
}
