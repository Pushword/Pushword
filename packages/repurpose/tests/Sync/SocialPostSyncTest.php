<?php

namespace Pushword\Repurpose\Tests\Sync;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Repurpose\Entity\SocialPost;
use Pushword\Repurpose\Repository\SocialPostRepository;
use Pushword\Repurpose\Sync\SocialPostSync;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The decisive P3 test: a carousel round-trips through the flat sync — export to a
 * pretty, multi-line JSON file, import it back byte-for-byte, and get swept when
 * the file is deleted (flat is authoritative). This is unproven ground: no
 * existing test covers a nested structure through the flat sync.
 */
#[Group('integration')]
final class SocialPostSyncTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private const string PAGE = 'demo/round-trip';

    private const string NETWORK = 'linkedin';

    private string $contentDir;

    private SocialPostSync $sync;

    private SocialPostRepository $repository;

    private EntityManagerInterface $em;

    /**
     * @return array<string, mixed>
     */
    private function spec(string $status = 'draft'): array
    {
        return [
            'page' => self::PAGE,
            'network' => self::NETWORK,
            'format' => 'linkedin-4-5',
            'status' => $status,
            'palette' => ['bg' => '#0b1120', 'text' => '#f8fafc', 'accent' => '#38bdf8'],
            'slides' => [
                ['layout' => 'bottom', 'title' => 'Slide one', 'image' => ['media' => '1.jpg', 'focusX' => 0.5, 'focusY' => 0.3, 'zoom' => 1.2]],
                ['layout' => 'center', 'title' => 'Slide two', 'paragraph' => 'A nested, several-levels-deep spec.'],
            ],
        ];
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->contentDir = sys_get_temp_dir().'/pw-social-sync-'.getmypid().'-'.abs(crc32(self::class));
        new Filesystem()->mkdir($this->contentDir);

        $siteConfig = $container->get(SiteRegistry::class)->switchSite(self::HOST)->get();
        $siteConfig->setCustomProperty('flat_content_dir', $this->contentDir);

        $finder = $container->get(FlatFileContentDirFinder::class);
        new ReflectionProperty(FlatFileContentDirFinder::class, 'contentDir')->setValue($finder, []);

        $this->sync = $container->get(SocialPostSync::class);
        $this->repository = $container->get(SocialPostRepository::class);
        $this->em = $container->get('doctrine.orm.default_entity_manager');

        // A clean slate for our key.
        $existing = $this->repository->findOneByKey(self::HOST, self::PAGE, self::NETWORK);
        if (null !== $existing) {
            $this->em->remove($existing);
            $this->em->flush();
        }
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->contentDir);
        parent::tearDown();
    }

    private function filePath(): string
    {
        return $this->contentDir.'/social-post/'.self::PAGE.'/'.self::NETWORK.'.json';
    }

    private function persist(SocialPost $post): void
    {
        $this->em->persist($post);
        $this->em->flush();
    }

    public function testExportWritesPrettyMultiLineJson(): void
    {
        $post = new SocialPost();
        $post->host = self::HOST;
        $post->setSpec($this->spec());
        $this->persist($post);

        $this->sync->export(self::HOST);

        $file = $this->filePath();
        self::assertFileExists($file, 'a nested slug nests into its own folder');
        $content = (string) file_get_contents($file);

        // Pretty-printed: the deep spec stays multi-line and diffable, not one blob.
        self::assertGreaterThan(15, substr_count($content, "\n"));
        self::assertSame($this->spec(), json_decode($content, true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testImportUpdatesTheDatabaseFromTheFile(): void
    {
        $post = new SocialPost();
        $post->host = self::HOST;
        $post->setSpec($this->spec('draft'));
        $this->persist($post);
        $this->sync->export(self::HOST);

        // Edit the file, then import: the file is authoritative.
        new Filesystem()->dumpFile($this->filePath(), json_encode($this->spec('posted'), \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
        $this->sync->import(self::HOST);
        $this->em->clear();

        $reloaded = $this->repository->findOneByKey(self::HOST, self::PAGE, self::NETWORK);
        self::assertNotNull($reloaded);
        self::assertSame('posted', $reloaded->getStatus());
    }

    public function testDeletingTheFileSweepsTheRow(): void
    {
        $post = new SocialPost();
        $post->host = self::HOST;
        $post->setSpec($this->spec());
        $this->persist($post);
        $this->sync->export(self::HOST);

        new Filesystem()->remove($this->filePath());
        $this->sync->import(self::HOST);
        $this->em->clear();

        self::assertNull(
            $this->repository->findOneByKey(self::HOST, self::PAGE, self::NETWORK),
            'a carousel whose file is gone is removed (flat is authoritative)',
        );
    }
}
