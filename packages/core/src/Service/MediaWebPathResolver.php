<?php

namespace Pushword\Core\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RequestContext;

//implements ResolverInterface
class MediaWebPathResolver extends \Liip\ImagineBundle\Imagine\Cache\Resolver\WebPathResolver
{
    public function __construct(
        Filesystem $filesystem,
        RequestContext $requestContext,
        $webRootDir,
        $cachePrefix = 'media'
    ) {
        $this->filesystem = $filesystem;
        $this->requestContext = $requestContext;
        $this->webRoot = rtrim(str_replace('//', '/', $webRootDir), '/');
        $this->cachePrefix = ltrim(str_replace('//', '/', $cachePrefix), '/');
        $this->cacheRoot = $this->webRoot.'/'.$this->cachePrefix;
    }

    public function resolve($path, $filter)
    {
        if (0 === strpos($path, 'media')) {
            $path = substr($path, 5);
        }
        if (0 === strpos($path, '/media')) {
            $path = substr($path, 6);
        }

        return '/'.$this->getFileUrl($path, $filter);
    }

    protected function getFilePath($path, $filter)
    {
        if (0 === strpos($path, 'media')) {
            $path = substr($path, 5);
        }
        if (0 === strpos($path, '/media')) {
            $path = substr($path, 6);
        }

        return $this->webRoot.'/'.$this->getFullPath($path, $filter);
    }

    private function getFullPath($path, $filter)
    {
        // crude way of sanitizing URL scheme ("protocol") part
        $path = str_replace('://', '---', $path);

        return $this->cachePrefix.'/'.$filter.'/'.ltrim($path, '/');
    }
}
