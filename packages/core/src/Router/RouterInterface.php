<?php

namespace Pushword\Core\Router;

use Pushword\Core\Entity\PageInterface;
use Symfony\Component\Routing\RouterInterface as SfRouterInterface;

interface RouterInterface
{
    /**
     * @var string
     */
    public const PATH = 'pushword_page';

    /**
     * @var string
     */
    public const CUSTOM_HOST_PATH = 'custom_host_pushword_page';

    public function generatePathForHomePage(PageInterface $page = null, bool $canonical = false): string;

    public function generate(
        PageInterface|string $slug = 'homepage',
        bool $canonical = false,
        int $pager = null,
        string $host = null
    ): string;

    public function setUseCustomHostPath(bool $useCustomHostPath = true): self;

    public function getRouter(): SfRouterInterface;
}
