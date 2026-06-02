<?php

namespace Pushword\Admin\Twig;

use Pushword\Core\Repository\PageRepository;
use Pushword\StaticGenerator\PushwordStaticGeneratorBundle;

use function Safe\json_encode;

use Twig\Attribute\AsTwigFunction;

class AdminExtension
{
    /** @var array<string, string> */
    private array $cache = [];

    public function __construct(
        private readonly PageRepository $pageRepository,
    ) {
    }

    #[AsTwigFunction('pw_all_tags_json')]
    public function getAllTagsJson(?string $host = null): string
    {
        $key = $host ?? '';

        return $this->cache[$key] ??= json_encode($this->pageRepository->getAllTags($host));
    }

    /**
     * Whether a page can be "held": meaningful whenever the static-generator
     * bundle is installed, since both the full static export (`pw:static`) and
     * `cache: static` mode keep the previously generated file while a hold is set.
     */
    #[AsTwigFunction('pw_page_holdable')]
    public function isHoldable(?string $host = null): bool
    {
        return class_exists(PushwordStaticGeneratorBundle::class);
    }
}
