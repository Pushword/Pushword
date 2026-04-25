<?php

namespace Pushword\StaticGenerator\Cache;

interface PageCacheGeneratorInterface
{
    public function generatePage(string $host, string $page): void;
}
