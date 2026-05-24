<?php

namespace Pushword\Snippet\Tests;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Snippet\Entity\Snippet;
use Pushword\Snippet\Twig\SnippetExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class SnippetRenderTest extends KernelTestCase
{
    public function testUnknownSnippetRendersEmpty(): void
    {
        self::bootKernel();
        $extension = self::getContainer()->get(SnippetExtension::class);

        self::assertSame('', $extension->renderSnippet('does-not-exist-'.uniqid()));
    }

    public function testEditorDefinitionsMergeComponentsAndContentSnippets(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $slug = 'editor-def-'.uniqid();
        $snippet = new Snippet();
        $snippet->host = 'localhost.dev';
        $snippet->setSlug($slug);
        $snippet->setName('Editor Def');
        $snippet->setContent('hi');

        $em->persist($snippet);
        $em->flush();

        $definitions = $container->get(SnippetExtension::class)->getEditorDefinitions('localhost.dev');

        // Dev component (schema-driven) is always present.
        self::assertArrayHasKey('cta', $definitions);
        self::assertNotEmpty($definitions['cta']['schema']);
        // Content snippet of the host is present with an empty (free-form) schema.
        self::assertArrayHasKey($slug, $definitions);
        self::assertSame([], $definitions[$slug]['schema']);

        $em->remove($snippet);
        $em->flush();
    }

    public function testComponentSnippetRendersTemplateWithParams(): void
    {
        self::bootKernel();
        $extension = self::getContainer()->get(SnippetExtension::class);

        $html = $extension->renderSnippet('cta', [
            'title' => 'Ready to start?',
            'buttonText' => 'Contact us',
            'buttonUrl' => '/contact',
        ]);

        self::assertStringContainsString('Ready to start?', $html);
        self::assertStringContainsString('Contact us', $html);
        self::assertStringContainsString('/contact', $html);
    }

    public function testContentSnippetRendersMarkdownAndParams(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        // Set a current page so the snippet renders through the real page filter
        // pipeline (Markdown → links → …), not the no-page fallback.
        $page = $em->getRepository(Page::class)->findOneBy([]);
        self::assertInstanceOf(Page::class, $page);
        $container->get(SiteRegistry::class)->setCurrentPage($page);

        $slug = 'greeting-'.uniqid();
        $snippet = new Snippet();
        $snippet->host = $page->host;
        $snippet->setSlug($slug);
        $snippet->setName('Greeting');
        $snippet->setContent('# Hello {{ params.name }}');

        $em->persist($snippet);
        $em->flush();

        $html = $container->get(SnippetExtension::class)->renderSnippet($slug, ['name' => 'World']);

        self::assertStringContainsString('<h1', $html);
        self::assertStringContainsString('Hello World', $html);

        $em->remove($snippet);
        $em->flush();
    }
}
