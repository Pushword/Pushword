<?php

namespace Pushword\Repurpose\Sync;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Sync\FlatSyncInterface;
use Pushword\Repurpose\Entity\SocialPost;
use Pushword\Repurpose\Repository\SocialPostRepository;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Bidirectional flat-file sync for {@see SocialPost}, discovered through the
 * `pushword.flat.sync` tag and driven by `pw:flat:sync` alongside Page/Media.
 *
 * Carousels live at `{flat_content_dir}/social-post/{page}/{network}.json`, one
 * pretty-printed JSON file per (page, network). JSON — not YAML frontmatter —
 * because the spec is several levels deep and `Yaml::dump()` flattens anything
 * below depth 2 into an unmergeable single line; `json_encode(PRETTY_PRINT)` stays
 * multi-line and diffable at any depth. The `social-post/` subfolder keeps the
 * files out of the page glob (which ignores non-`.md`/`.csv` entries anyway).
 *
 * The authoritative `page`/`network` live inside the JSON; the path is a
 * deterministic handle regenerated on export, never parsed on import — so a page
 * slug containing `_`, `.` or `/` is never mis-split.
 */
final readonly class SocialPostSync implements FlatSyncInterface
{
    private const string DIR = 'social-post';

    private Filesystem $filesystem;

    public function __construct(
        private SiteRegistry $apps,
        private FlatFileContentDirFinder $contentDirFinder,
        private SocialPostRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function getEntityName(): string
    {
        return 'social-post';
    }

    /**
     * Auto mode: export first so admin/API-created rows gain a file, then let the
     * files drive the database (flat stays authoritative on flat-file sites).
     */
    public function sync(?string $host = null, bool $forceExport = false): void
    {
        if ($forceExport) {
            $this->export($host);

            return;
        }

        $this->export($host);
        $this->import($host);
    }

    public function export(?string $host = null): void
    {
        foreach ($this->hosts($host) as $eachHost) {
            $dir = $this->dir($eachHost);
            foreach ($this->repository->findByHost($eachHost) as $post) {
                $this->filesystem->dumpFile($this->pathFor($dir, $post->getPage(), $post->getNetwork()), $this->encode($post->getSpec()));
            }
        }
    }

    public function import(?string $host = null): void
    {
        foreach ($this->hosts($host) as $eachHost) {
            $this->importHost($eachHost);
        }
    }

    private function importHost(string $host): void
    {
        $dir = $this->dir($host);
        $seen = [];

        if (is_dir($dir)) {
            foreach (new Finder()->files()->in($dir)->name('*.json') as $file) {
                $spec = json_decode($file->getContents(), true);
                if (! \is_array($spec)) {
                    continue;
                }

                /** @var array<string, mixed> $spec */
                $page = \is_string($spec['page'] ?? null) ? $spec['page'] : '';
                $network = \is_string($spec['network'] ?? null) ? $spec['network'] : '';
                if ('' === $page) {
                    continue;
                }

                if ('' === $network) {
                    continue;
                }

                $post = $this->repository->findOneByKey($host, $page, $network) ?? new SocialPost();
                $post->host = $host;
                $post->setSpec($spec);
                $this->entityManager->persist($post);
                $seen[$page.' '.$network] = true;
            }
        }

        // Delete rows whose file has gone (flat is authoritative).
        foreach ($this->repository->findByHost($host) as $post) {
            if (! isset($seen[$post->getPage().' '.$post->getNetwork()])) {
                $this->entityManager->remove($post);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * @return list<string>
     */
    private function hosts(?string $host): array
    {
        return null !== $host ? [$host] : array_values($this->apps->getHosts());
    }

    private function dir(string $host): string
    {
        return $this->contentDirFinder->get($host).'/'.self::DIR;
    }

    private function pathFor(string $dir, string $page, string $network): string
    {
        return $dir.'/'.trim($page, '/').'/'.$network.'.json';
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function encode(array $spec): string
    {
        return json_encode($spec, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n";
    }
}
