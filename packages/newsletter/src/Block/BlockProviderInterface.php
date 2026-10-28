<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Block;

use Pushword\Newsletter\Event\CampaignIssue;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('pushword.newsletter.block_provider')]
interface BlockProviderInterface
{
    /** Stable storage key, e.g. "catalogue". */
    public function type(): string;

    /** Translation key or display name. */
    public function label(): string;

    /** @return list<BlockContent> At most 25 matches; never resolve arbitrary templates. */
    public function search(string $query, BlockContext $context): array;

    /** @param array<string, mixed> $parameters */
    public function resolve(string $reference, array $parameters, BlockContext $context): ?BlockContent;

    /** @return list<CampaignIssue> Validate the saved selection without replacing authored content. */
    public function check(BlockContent $block, BlockContext $context): array;
}
