<?php

declare(strict_types=1);

namespace Pushword\Core\Service\Toc;

use Symfony\Component\String\Slugger\AsciiSlugger;
use TOC\SluggerInterface;

/** Preserve TOC\UniqueSlugger results while avoiding repeated suffix searches. */
final class IndexedUniqueSlugger implements SluggerInterface
{
    private readonly AsciiSlugger $slugger;

    /** @var array<string, string> */
    private array $bases = [];

    /** @var array<string, true> */
    private array $used = [];

    /** @var list<string> */
    private array $numeric = [];

    /** @var array<string, int> */
    private array $next = [];

    public function __construct()
    {
        $this->slugger = new AsciiSlugger();
    }

    public function makeSlug(string $string): string
    {
        $base = $this->bases[$string] ??= $this->slugger->slug($string)->lower()->toString();
        $candidate = $base;
        $suffix = $this->next[$base] ?? 1;
        while (isset($this->used[$candidate]) || $this->numericCollision($candidate)) {
            $candidate = $base.'-'.$suffix++;
        }

        $this->next[$base] = $suffix;
        $this->used[$candidate] = true;
        if (is_numeric($candidate)) {
            // Upstream deliberately uses loose comparisons: "01" collides with "1".
            $this->numeric[] = $candidate;
        }

        return $candidate;
    }

    private function numericCollision(string $candidate): bool
    {
        if (! is_numeric($candidate)) {
            return false;
        }

        return array_any($this->numeric, static fn (string $used): bool => 0 === ($candidate <=> $used));
    }

    public function reset(): void
    {
        $this->bases = [];
        $this->used = [];
        $this->numeric = [];
        $this->next = [];
    }
}
