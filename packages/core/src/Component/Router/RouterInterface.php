<?php

namespace Pushword\Core\Component\Router;

use Pushword\Core\Entity\PageInterface;
use Symfony\Component\Routing\RouterInterface as SfRouterInterface;

interface RouterInterface
{
    const PATH = 'pushword_page';

    const CUSTOM_HOST_PATH = 'custom_host_pushword_page';

    public function generatePathForHomePage(?PageInterface $page = null, $canonical = false): string;

    /**
     * @param string|PageInterface $slug
     */
    public function generate($slug = 'homepage', $canonical = false, $pager = null): string;

    public function setUseCustomHostPath($useCustomHostPath);

    public function getRouter(): SfRouterInterface;
}
