<?php

namespace Pushword\StaticGenerator\Generator;

use Pushword\Core\Entity\Page;

class RedirectionManager extends AbstractGenerator
{
    /**
     * Used in .htaccess generation.
     *
     * @var array<int, array{0: string, 1: string, 2: int}> where 0 is the from, 1 is the to, 2 is the code
     */
    protected $redirections = [];

    /**
     * The function cache redirection found during generatePages.
     */
    public function add(string $from, string $to = '', int $code = 0): void
    {
        $this->redirections[] = [$from, $to, $code];
    }

    public function addPage(Page $page): void
    {
        $this->redirections[] = [
            $this->router->generate($page->getRealSlug()),
            $page->getRedirection(),
            $page->getRedirectionCode(),
        ];
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: int}> where 0 is the from, 1 is the to, 2 is the code
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
