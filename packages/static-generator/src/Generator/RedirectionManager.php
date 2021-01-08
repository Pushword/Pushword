<?php

namespace Pushword\StaticGenerator\Generator;

use Pushword\Core\Entity\PageInterface as Page;

class RedirectionManager extends AbstractGenerator
{
    /**
     * Used in .htaccess generation.
     *
     * @var array
     */
    protected $redirections = [];

    /**
     * The function cache redirection found during generatePages.
     */
    public function add($from, string $to = '', $code = 0): void
    {
        $this->redirections[] = [
            $from instanceof Page ? $this->router->generate($from->getRealSlug()) : $from,
            $to ?: $from->getRedirection(),
            $code ?: $from->getRedirectionCode(),
        ];
    }

    public function get(): array
    {
        return $this->redirections;
    }

    public function reset(): void
    {
        $this->redirections = [];
    }
}
