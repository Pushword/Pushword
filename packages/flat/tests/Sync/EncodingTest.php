<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Sync;

use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Sync\PageSync;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class EncodingTest extends KernelTestCase
{
    private EntityManager $em;

    private PageSync $pageSync;

    private string $contentDir;

    /** @var string[] */
    private array $createdFiles = [];

    private Filesystem $filesystem;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->filesystem = new Filesystem();

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->em = $em;

        /** @var PageSync $pageSync */
        $pageSync = self::getContainer()->get(PageSync::class);
        $this->pageSync = $pageSync;

        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $this->contentDir = $contentDirFinder->get('localhost.dev');

        // Export existing pages so import won't delete them
        $this->pageSync->export('localhost.dev', true, $this->contentDir);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }

        // Clean up test pages
        foreach (['utf8-bom-test', 'accented-test', 'cjk-test', 'emoji-test', 'special-yaml-test', 'cafe-creme', 'js-guide-test', 'smart-quotes-test'] as $slug) {
            $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => $slug, 'host' => 'localhost.dev']);
            if ($page instanceof Page) {
                $this->em->remove($page);
            }
        }

        $this->em->flush();
        parent::tearDown();
    }

    private function createMd(string $fileName, string $content): void
    {
        $path = $this->contentDir.'/'.$fileName;
        $this->filesystem->dumpFile($path, $content);
        touch($path, time() + 100);
        $this->createdFiles[] = $path;
    }

    public function testUtf8BomInMarkdownFile(): void
    {
        $bom = "\xEF\xBB\xBF";
        $this->createMd('utf8-bom-test.md', $bom."---\nh1: 'BOM Test Page'\n---\n\nContent after BOM");

        $this->pageSync->import('localhost.dev');

        $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'utf8-bom-test', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page);
        // H1 should not contain BOM characters
        self::assertStringNotContainsString($bom, $page->getH1());
        self::assertStringContainsString('BOM Test Page', $page->getH1());
    }

    public function testAccentedCharactersInFrontmatter(): void
    {
        $this->createMd('accented-test.md', "---\nh1: 'Échange et coopération'\n---\n\nContenu avec des accents: à é ï ô ü");

        $this->pageSync->import('localhost.dev');

        $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'accented-test', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page);
        self::assertSame('Échange et coopération', $page->getH1());

        // Round-trip: export and re-import
        $this->pageSync->export('localhost.dev', true, $this->contentDir);
        $exportedContent = $this->filesystem->readFile($this->contentDir.'/accented-test.md');
        self::assertStringContainsString('Échange et coopération', $exportedContent);
    }

    public function testCjkCharactersInContent(): void
    {
        $cjkContent = '这是中文内容。日本語のコンテンツ。한국어 콘텐츠입니다.';
        $this->createMd('cjk-test.md', "---\nh1: 'CJK Test'\n---\n\n".$cjkContent);

        $this->pageSync->import('localhost.dev');

        $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'cjk-test', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page);
        self::assertStringContainsString('这是中文内容', $page->getMainContent());
        self::assertStringContainsString('日本語のコンテンツ', $page->getMainContent());
    }

    public function testEmojiInFrontmatterValues(): void
    {
        $this->createMd('emoji-test.md', "---\nh1: 'Emoji Test'\ntags: 'fun 🎉 coding 💻'\n---\n\nEmoji content 🚀");

        $this->pageSync->import('localhost.dev');

        $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'emoji-test', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page);
        self::assertStringContainsString('🎉', $page->getTags());
        self::assertStringContainsString('🚀', $page->getMainContent());
    }

    public function testSpecialYamlCharactersInH1(): void
    {
        $this->createMd('special-yaml-test.md', "---\nh1: \"Test: A Page's Title\"\n---\n\nContent here");

        $this->pageSync->import('localhost.dev');

        $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'special-yaml-test', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page);
        self::assertSame("Test: A Page's Title", $page->getH1());
    }

    /**
     * @see https://github.com/.../issues/5
     * finfo misdetects .md files containing JS code examples as application/javascript,
     * causing them to be silently skipped during import.
     */
    public function testMarkdownFileWithJsCodeBlockIsImported(): void
    {
        $content = <<<'MD'
            ---
            h1: "JavaScript Guide"
            ---

            Here is an example:

            <pre><code>
            "use strict";
            var express = require("express");
            var app = express();
            </code></pre>
            MD;

        // Verify finfo actually misdetects this content
        $tmpFile = tempnam(sys_get_temp_dir(), 'md_finfo_');
        file_put_contents($tmpFile, $content);
        $finfo = finfo_open(\FILEINFO_MIME_TYPE);
        self::assertNotEmpty($finfo);
        $mime = (string) finfo_file($finfo, $tmpFile);
        unlink($tmpFile);
        self::assertSame('application/javascript', $mime, 'Precondition: finfo should misdetect this .md content as JS');

        $this->createMd('js-guide-test.md', $content);

        $this->pageSync->import('localhost.dev');

        $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'js-guide-test', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page, '.md file misdetected as application/javascript should still be imported');
    }

    public function testSmartQuotesNormalizedOnExport(): void
    {
        // Import a page with smart/typographic quotes (as AI tools would produce)
        $this->createMd('smart-quotes-test.md', "---\nh1: \"L\u{2019}activité de l\u{2019}été\"\n---\n\nIl a dit \u{201C}bonjour\u{201D} et l\u{2018}ami\u{2019} est parti.");

        $this->pageSync->import('localhost.dev');

        $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'smart-quotes-test', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page);

        // Export and verify smart quotes are normalized to straight quotes
        $this->pageSync->export('localhost.dev', true, $this->contentDir);
        $exported = $this->filesystem->readFile($this->contentDir.'/smart-quotes-test.md');

        self::assertStringNotContainsString("\u{2018}", $exported);
        self::assertStringNotContainsString("\u{2019}", $exported);
        self::assertStringNotContainsString("\u{201C}", $exported);
        self::assertStringNotContainsString("\u{201D}", $exported);
        self::assertStringContainsString("L'activit", $exported);
        self::assertStringContainsString('"bonjour"', $exported);
    }

    public function testNonAsciiSlugFromFileName(): void
    {
        $this->createMd('café-crème.md', "---\nh1: 'Café Crème'\n---\n\nFrench coffee");

        $this->pageSync->import('localhost.dev');

        // Slug should be normalized from the filename
        $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'cafe-creme', 'host' => 'localhost.dev']);
        if (null === $page) {
            // The slug might keep the accents depending on normalizer
            $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'café-crème', 'host' => 'localhost.dev']);
        }

        self::assertInstanceOf(Page::class, $page, 'Page from non-ASCII filename should be imported');
        self::assertSame('Café Crème', $page->getH1());
    }
}
