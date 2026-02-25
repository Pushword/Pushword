<?php

namespace Pushword\Admin\Twig;

use Pushword\Core\Repository\PageRepository;

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
}
