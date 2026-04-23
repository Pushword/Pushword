<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Cache;

interface PageCacheGeneratorInterface
{
    public function generatePage(string $host, string $page): void;
}
