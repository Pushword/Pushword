<?php

namespace Pushword\Newsletter\Trigger;

use Pushword\Newsletter\Entity\Automation;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Traversable;

/**
 * Every {@see TriggerSource} the application has, by name.
 *
 * An automation stores its source as a string rather than as an enum so a bundle
 * can add one without editing this package. The cost is that the name may not
 * resolve — an automation left behind by a bundle that was removed — and that is
 * reported rather than fatal: {@see self::find()} returns null, and the caller
 * decides whether an unknown source is a quiet automation or a form error.
 */
final class TriggerSourceRegistry
{
    /** @var array<string, TriggerSource>|null */
    private ?array $sources = null;

    /** @param Traversable<TriggerSource> $tagged */
    public function __construct(
        #[AutowireIterator('pushword.newsletter.trigger_source')]
        private readonly Traversable $tagged,
    ) {
    }

    public function find(string $name): ?TriggerSource
    {
        return $this->all()[$name] ?? null;
    }

    /** The source an automation runs on, or null when nothing answers to its name. */
    public function for(Automation $automation): ?TriggerSource
    {
        return $this->find($automation->source);
    }

    /** @return array<string, TriggerSource> */
    public function all(): array
    {
        if (null !== $this->sources) {
            return $this->sources;
        }

        $sources = [];

        foreach ($this->tagged as $source) {
            $sources[$source->name()] = $source;
        }

        ksort($sources);

        return $this->sources = $sources;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->all());
    }
}
