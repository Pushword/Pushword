<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Block;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class BlockRegistry
{
    /** @var array<string, BlockProviderInterface> */
    private array $providers;

    /** @param iterable<BlockProviderInterface> $providers */
    public function __construct(#[AutowireIterator('pushword.newsletter.block_provider')] iterable $providers)
    {
        $indexed = [];
        foreach ($providers as $provider) {
            $type = $provider->type();
            if (isset($indexed[$type])) {
                throw new InvalidArgumentException('Duplicate newsletter block provider: '.$type);
            }

            $indexed[$type] = $provider;
        }

        $this->providers = $indexed;
    }

    /** @return array<string, string> type => label */
    public function choices(): array
    {
        return array_map(static fn (BlockProviderInterface $provider): string => $provider->label(), $this->providers);
    }

    public function get(string $type): BlockProviderInterface
    {
        return $this->providers[$type] ?? throw new InvalidArgumentException('Unknown newsletter block provider: '.$type);
    }
}
