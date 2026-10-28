<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Block;

use Pushword\Newsletter\Entity\EmailBlock;
use Pushword\Newsletter\Repository\EmailBlockRepository;

final readonly class LibraryBlockProvider implements BlockProviderInterface
{
    public function __construct(private EmailBlockRepository $blocks)
    {
    }

    public function type(): string
    {
        return 'library';
    }

    public function label(): string
    {
        return 'newsletter.block.library';
    }

    public function search(string $query, BlockContext $context): array
    {
        return array_map($this->content(...), $this->blocks->search($query));
    }

    public function resolve(string $reference, array $parameters, BlockContext $context): ?BlockContent
    {
        $block = ctype_digit($reference) ? $this->blocks->find((int) $reference) : null;

        return null === $block ? null : $this->content($block);
    }

    public function check(BlockContent $block, BlockContext $context): array
    {
        // Library inserts are independent copies, even after deleting their source.
        return [];
    }

    private function content(EmailBlock $block): BlockContent
    {
        return new BlockContent((string) $block->id, $block->name, $block->bodyMarkdown);
    }
}
