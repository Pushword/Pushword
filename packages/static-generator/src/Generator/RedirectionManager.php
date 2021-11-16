<?php

namespace Pushword\StaticGenerator\Generator;

use Pushword\Core\Entity\PageInterface;

class RedirectionManager extends AbstractGenerator
{
    /**
     * Used in .htaccess generation.
     *
     * @var array<int, array<mixed>>
     */
    protected $redirections = [];

    /**
     * The function cache redirection found during generatePages.
     */
    public function add(string $from, string $to = '', int $code = 0): void
    {
        $this->redirections[] = [$from, $to, $code];
    }

    public function addPage(PageInterface $page): void
    {
        $this->redirections[] = [
            $this->router->generate($page->getRealSlug()),
            $page->getRedirection(),
            $page->getRedirectionCode(),
        ];
    }

    /**
     * @return array<int, array<mixed>>
     */
    public function get(): array
    {
        return $this->redirections;
    }

    public function reset(): void
    {
        $this->redirections = [];
    }
}
