<?php

namespace Pushword\Core\Service\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;
use Psr\Cache\CacheItemPoolInterface;
use Pushword\Core\Component\EntityFilter\Filter\Date;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Service\Markdown\Extension\PushwordExtension;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;
use Twig\Attribute\AsTwigFilter;

class MarkdownParser
{
    /**
     * Bump when the converter configuration or extensions change in a way that
     * alters output, to invalidate previously cached fragments.
     */
    private const int CACHE_VERSION = 2;

    private readonly MarkdownConverter $converter;

    private ?string $cacheVersion = null;

    public function __construct(
        LinkProvider $linkProvider,
        MediaExtension $mediaExtension,
        SiteRegistry $apps,
        #[Autowire(service: 'cache.pushword_markdown')]
        private readonly ?CacheItemPoolInterface $cache = null,
        #[Autowire(service: 'cache.app')]
        private readonly ?CacheItemPoolInterface $versionCache = null,
    ) {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AttributesExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new TaskListExtension());
        $environment->addExtension(new PushwordExtension(
            $linkProvider,
            $mediaExtension,
            $apps,
            new Date($apps),
        ));

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Convert markdown to HTML.
     *
     * The input is the post-Twig block text, so any dynamic content (snippets,
     * page lists, galleries) is already baked into $text and thus into the cache
     * key. The only convert-time dependency left is media (image rendering),
     * covered by the media version token — but only for fragments that actually
     * contain a Markdown image (see cacheKeyVersion()). date() shortcodes are
     * cached as-is: the slight staleness is acceptable and the fragment refreshes
     * whenever the page is saved or (for image fragments) the media version bumps.
     */
    #[AsTwigFilter('markdown', isSafe: ['html'])]
    public function transform(string $text): string
    {
        if (null === $this->cache) {
            return $this->converter->convert($text)->__toString();
        }

        try {
            $item = $this->cache->getItem('pw_md.'.hash('xxh3', $this->cacheKeyVersion($text).'|'.$text));
            if ($item->isHit()) {
                /** @var string */
                return $item->get();
            }

            $html = $this->converter->convert($text)->__toString();
            $item->set($html);
            $this->cache->save($item);

            return $html;
        } catch (Throwable) {
            // A cache backend hiccup must never break rendering.
            return $this->converter->convert($text)->__toString();
        }
    }

    /**
     * Version token for a fragment's cache key.
     *
     * Image rendering (Markdown `![](…)` → ImageRenderer → media table) is the
     * only convert-time media dependency. A fragment with no Markdown image is
     * media-independent: it keeps the bare parser version and stays cached across
     * media writes. Only image-bearing fragments mix in the media version. Raw
     * `<img>` HTML (e.g. from gallery shortcodes already expanded by Twig) is
     * emitted verbatim by CommonMark, so it is media-independent here too.
     */
    private function cacheKeyVersion(string $text): string
    {
        if (! str_contains($text, '![')) {
            return (string) self::CACHE_VERSION;
        }

        return $this->cacheVersion();
    }

    /**
     * Version token mixing the parser's own version with the media version, so
     * cached fragments are invalidated whenever a media changes (image rendering
     * depends on the media table). The media version is read from cache.app — the
     * counter the media lifecycle listener bumps on every write — never via a DB
     * query, so this stays safe to call mid-render while another result set is
     * being iterated (e.g. during pw:static).
     */
    private function cacheVersion(): string
    {
        if (null !== $this->cacheVersion) {
            return $this->cacheVersion;
        }

        $mediaVersion = 0;
        if (null !== $this->versionCache) {
            $item = $this->versionCache->getItem(MediaRepository::VERSION_CACHE_KEY);
            $mediaVersion = $item->isHit() && \is_int($item->get()) ? $item->get() : 0;
        }

        return $this->cacheVersion = self::CACHE_VERSION.'m'.$mediaVersion;
    }
}
