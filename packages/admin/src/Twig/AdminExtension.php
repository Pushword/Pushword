<?php

namespace Pushword\Admin\Twig;

use Pushword\Core\Repository\PageRepository;

use function Safe\json_encode;

use Twig\Attribute\AsTwigFunction;

class AdminExtension
{
    public function __construct(
        private readonly PageRepository $pageRepository,
    ) {
    }

    /**
     * Get all tags as JSON string for suggestions.
     */
    #[AsTwigFunction('pw_all_tags_json')]
    public function getAllTagsJson(?string $host = null): string
    {
        return json_encode($this->pageRepository->getAllTags($host));
    }
}
