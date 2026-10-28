<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Admin;

use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Repurpose\Entity\SocialPost;
use Twig\Attribute\AsTwigFunction;

final readonly class SourcePageResolver
{
    public function __construct(private PageRepository $pages)
    {
    }

    #[AsTwigFunction('repurpose_source_page')]
    public function resolve(SocialPost $post): ?Page
    {
        if ('' === $post->page || str_starts_with($post->page, 'standalone/')) {
            return null;
        }

        return $this->pages->findOneBy(['host' => $post->host, 'slug' => $post->page]);
    }
}
