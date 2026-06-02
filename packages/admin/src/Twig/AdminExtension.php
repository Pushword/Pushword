<?php

namespace Pushword\Admin\Twig;

use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\SiteRegistry;

use function Safe\json_encode;

use Twig\Attribute\AsTwigFunction;

class AdminExtension
{
    /** @var array<string, string> */
    private array $cache = [];

    /** @var array<string, bool> */
    private array $holdableCache = [];

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly SiteRegistry $apps,
    ) {
    }

    #[AsTwigFunction('pw_all_tags_json')]
    public function getAllTagsJson(?string $host = null): string
    {
        $key = $host ?? '';

        return $this->cache[$key] ??= json_encode($this->pageRepository->getAllTags($host));
    }

    /**
     * Whether a page on this host can be "held": only meaningful when the host
     * is served from a static/cache build, where edits stay out of production
     * until the hold is released.
     */
    #[AsTwigFunction('pw_page_holdable')]
    public function isHoldable(?string $host = null): bool
    {
        $key = $host ?? '';

        return $this->holdableCache[$key] ??= 'static' === $this->apps->getAppValue('cache', $key);
    }
}
