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

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }

        // Clean up test pages
        foreach (['utf8-bom-test', 'accented-test', 'cjk-test', 'emoji-test', 'special-yaml-test', 'cafe-creme'] as $slug) {
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
