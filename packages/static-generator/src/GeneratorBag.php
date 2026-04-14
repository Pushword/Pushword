<?php

namespace Pushword\StaticGenerator;

use LogicException;
use Pushword\StaticGenerator\Generator\GeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class GeneratorBag
{
    /** @var array<string, GeneratorInterface> */
    private array $bag = [];

    /**
     * @param iterable<GeneratorInterface> $generators
     */
    public function __construct(
        #[AutowireIterator('pushword.static_generator')]
        iterable $generators,
    ) {
        foreach ($generators as $generator) {
            $this->bag[$generator::class] = $generator;
        }
    }

    public function get(string $name): GeneratorInterface
    {
        return $this->bag[$name] ?? throw new LogicException(\sprintf('Generator "%s" is not registered. Did you forget to tag it with "pushword.static_generator"?', $name));
    }
}
