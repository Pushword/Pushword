<?php

declare(strict_types=1);

namespace Pushword\Core\Service\Markdown;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Pushword\Core\Component\EntityFilter\Filter\Date;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Service\NativeWorker;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;
use Twig\Attribute\AsTwigFilter;
use Twig\Environment as Twig;

class MarkdownParser implements ResetInterface
{
    /**
     * Bump when the renderer configuration changes in a way that
     * alters output, to invalidate previously cached fragments.
     */
    private const int CACHE_VERSION = 21;

    private const int NATIVE_CACHE_VERSION = 3;

    private readonly Date $dateFilter;

    private readonly TempestMarkdownRenderer $tempestRenderer;

    private ?string $cacheVersion = null;

    private readonly ?NativeWorker $nativeWorker;

    private bool $nativeFailed = false;

    public function __construct(
        private readonly LinkProvider $linkProvider,
        MediaExtension $mediaExtension,
        private readonly SiteRegistry $apps,
        Twig $twig,
        #[Autowire(service: 'cache.pushword_markdown')]
        private readonly ?CacheItemPoolInterface $cache = null,
        #[Autowire(service: 'cache.app')]
        private readonly ?CacheItemPoolInterface $versionCache = null,
        #[Autowire(param: 'pw.native_markdown_renderer')]
        ?string $nativeBinary = null,
        #[Autowire(param: 'pw.native_content_analyzer_timeout')]
        float $nativeTimeout = 5.0,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->nativeWorker = null !== $nativeBinary && '' !== $nativeBinary ? new NativeWorker($nativeBinary, $nativeTimeout) : null;
        $this->tempestRenderer = new TempestMarkdownRenderer($this->linkProvider, $apps, $twig, $mediaExtension);
        $this->dateFilter = new Date($apps);
    }

    /**
     * Convert markdown to HTML, blocks included.
     */
    #[AsTwigFilter('markdown', isSafe: ['html'])]
    public function transform(string $text): string
    {
        return $this->convertCached('pw_md.', $text, false);
    }

    public function hasNativeMarkdown(): bool
    {
        return null !== $this->nativeWorker && ! $this->nativeFailed;
    }

    /**
     * Probe already-prepared Markdown blocks in one request. Cache hits and
     * accepted native results are strings; declines are left to the PHP caller.
     *
     * @param list<string> $texts
     *
     * @return list<?string>
     */
    public function renderNativeMany(array $texts): array
    {
        $results = array_fill(0, \count($texts), null);
        $worker = $this->nativeWorker;
        if ([] === $texts || null === $worker || $this->nativeFailed) {
            return $results;
        }

        $pending = [];
        $keys = [];
        foreach ($texts as $index => $text) {
            $hash = hash('xxh3', $this->cacheKeyVersion($text).'|'.$text);
            $keys[$index] = 'pw_mdn'.self::NATIVE_CACHE_VERSION.'.'.$hash;

            try {
                foreach (['pw_md.'.$hash, $keys[$index]] as $key) {
                    $item = $this->cache?->getItem($key);
                    if (null !== $item && $item->isHit() && \is_string($item->get())) {
                        $results[$index] = $item->get();

                        continue 2;
                    }
                }
            } catch (Throwable) {
                // Cache availability does not affect rendering.
            }

            $pending[$index] = [
                'markdown' => $text,
                'fenced_code_pre_class' => $this->apps->get()->getStr(SiteConfig::FENCED_CODE_PRE_CLASS),
                'locale' => $this->apps->getLocale(),
                'allow_obfuscated_links' => $this->linkProvider->canRenderObfuscatedMarkdownLinkNatively(),
            ];
            if (str_contains($text, 'date(')) {
                $dateValues = $this->dateValues($text);
                if ([] !== $dateValues) {
                    $pending[$index]['date_values'] = $dateValues;
                }
            }
        }

        if ([] === $pending) {
            return array_values($results);
        }

        try {
            $response = $worker->request('render_markdown', array_values($pending));
            foreach ($response as $html) {
                if (null !== $html && ! \is_string($html)) {
                    throw new RuntimeException('Invalid native Markdown response.');
                }
            }
        } catch (Throwable $throwable) {
            $worker->reset();
            $this->nativeFailed = true;
            $this->logger->warning('Native Markdown rendering failed; using PHP until the service is reset.', ['exception' => $throwable]);

            return array_values($results);
        }

        foreach (array_keys($pending) as $offset => $index) {
            $html = $response[$offset];
            $results[$index] = $html;
            if (null === $html) {
                continue;
            }

            try {
                $item = $this->cache?->getItem($keys[$index]);
                if (null !== $item) {
                    $item->set($html);
                    $this->cache->save($item);
                }
            } catch (Throwable) {
                // The computed result remains usable without a cache write.
            }
        }

        return array_values($results);
    }

    /** @return array<string, string> */
    private function dateValues(string $text): array
    {
        preg_match_all('/date\([^)]+\)/', $text, $matches);
        $values = [];
        foreach (array_unique($matches[0]) as $shortcode) {
            $values[$shortcode] = $this->dateFilter->convertDateShortCode($shortcode, $this->apps->get()->locale);
        }

        return $values;
    }

    public function reset(): void
    {
        $this->nativeWorker?->reset();
        $this->nativeFailed = false;
        $this->cacheVersion = null;
    }

    /**
     * Convert markdown to HTML without ever emitting a block tag — no `<p>`.
     *
     * For short texts injected inside existing markup (a component lede, a
     * caption, a subtitle): links, emphasis, inline code, strikethrough,
     * `{attributes}`, raw inline HTML and Pushword inline shortcodes are
     * rendered; block syntax (`#`, `-`, `>`, tables…) stays literal text and
     * blank lines don't create paragraphs — meant for one-line inputs.
     */
    #[AsTwigFilter('markdown_inline', isSafe: ['html'])]
    public function transformInline(string $text): string
    {
        return trim($this->convertCached('pw_mdi.', $text, true));
    }

    /**
     * The input is the post-Twig block text, so any dynamic content (snippets,
     * page lists, galleries) is already baked into $text and thus into the cache
     * key. The only convert-time dependency left is media (image rendering),
     * covered by the media version token — but only for fragments that actually
     * contain a Markdown image (see cacheKeyVersion()). date() shortcodes are
     * cached as-is: the slight staleness is acceptable and the fragment refreshes
     * whenever the page is saved or (for image fragments) the media version bumps.
     *
     * Baking the rendered output into the key is also why **a Twig function callable
     * from a page body has to be deterministic**: draw an id from random(), and every
     * render of every page holding the call writes a fresh entry that nothing will
     * ever read back. The pool grows without bound, never hits, and a static build
     * rewrites files whose content did not change. `{{ reviews() }}` did exactly that
     * until rc852 — {@see \Pushword\Conversation\Tests\Twig\ReviewListDeterminismTest}.
     */
    private function convertCached(string $keyPrefix, string $text, bool $inline): string
    {
        if (null === $this->cache) {
            return $this->convert($text, $inline);
        }

        try {
            $item = $this->cache->getItem($keyPrefix.hash('xxh3', $this->cacheKeyVersion($text).'|'.$text));
            if ($item->isHit()) {
                /** @var string */
                return $item->get();
            }
        } catch (Throwable) {
            // A cache backend hiccup must never break rendering.
            return $this->convert($text, $inline);
        }

        $html = $this->convert($text, $inline);

        try {
            $item->set($html);
            $this->cache->save($item);
        } catch (Throwable) {
            // Rendering succeeded even if the cache write did not.
        }

        return $html;
    }

    private function convert(string $text, bool $inline): string
    {
        $html = $inline ? $this->tempestRenderer->renderInline($text) : $this->tempestRenderer->render($text);
        if (null === $html) {
            throw new RuntimeException('Tempest cannot render Markdown: '.json_encode(mb_substr($text, 0, 160), \JSON_INVALID_UTF8_SUBSTITUTE));
        }

        return $html;
    }

    /**
     * Version token for a fragment's cache key.
     *
     * Image rendering (Markdown `![](…)` → ImageRenderer → media table) is the
     * only convert-time media dependency. A fragment with no Markdown image is
     * media-independent: it keeps the bare parser version and stays cached across
     * media writes. Only image-bearing fragments mix in the media version. Raw
     * `<img>` HTML (e.g. from gallery shortcodes already expanded by Twig) is
     * emitted verbatim by the Markdown renderer, so it is media-independent here too.
     *
     * A fragment also carries whichever per-site render setting its own syntax can
     * reach: `body_image_sizes` for an image, `fenced_code_pre_class` for a fenced
     * block. This pool is content-keyed and shared by every host, so without them
     * two apps rendering the same markdown collide on one entry, and editing a
     * value never invalidates anything — the pool lives beside the cache dir
     * precisely to survive cache:clear. Appending them only where the syntax
     * appears is what keeps the invalidation surgical, and what lets a fragment
     * reaching neither still serve every host from one entry.
     */
    private function cacheKeyVersion(string $text): string
    {
        $version = (string) self::CACHE_VERSION;

        if (str_contains($text, '![')) {
            $version = $this->cacheVersion().'s'.$this->apps->get()->getStr(SiteConfig::BODY_IMAGE_SIZES);
        }

        if (str_contains($text, '```') || str_contains($text, '~~~')) {
            $version .= 'p'.$this->apps->get()->getStr(SiteConfig::FENCED_CODE_PRE_CLASS);
        }

        $phone = (str_contains($text, '0') || str_contains($text, '+33'))
            && 1 === preg_match('/(?:(?:\+|00)33|0)(?:\s|&nbsp;|\xC2\xA0)*[1-9](?:(?:[\s.-]|&nbsp;|\xC2\xA0)*\d{2}){4}/u', $text);
        if (str_contains($text, '#[') || $phone) {
            $version .= 'a'.(int) $this->linkProvider->canRenderObfuscatedMarkdownLinkNatively();
        }

        if ($phone) {
            $version .= 'l'.$this->apps->getLocale();
        }

        return $version;
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
