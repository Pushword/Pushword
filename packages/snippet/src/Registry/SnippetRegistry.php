<?php

namespace Pushword\Snippet\Registry;

use LogicException;
use Pushword\Snippet\Attribute\AsSnippet;
use Pushword\Snippet\Component\SnippetComponentInterface;
use ReflectionClass;

final class SnippetRegistry
{
    /** @var array<string, array{component: SnippetComponentInterface, template: string, label: string}> */
    private array $components = [];

    /**
     * @param iterable<SnippetComponentInterface> $components
     */
    public function __construct(iterable $components)
    {
        foreach ($components as $component) {
            $attributes = new ReflectionClass($component)->getAttributes(AsSnippet::class);
            if ([] === $attributes) {
                throw new LogicException(\sprintf('"%s" must declare the #[AsSnippet] attribute.', $component::class));
            }

            $attribute = $attributes[0]->newInstance();
            $this->components[$attribute->name] = [
                'component' => $component,
                'template' => $attribute->template,
                'label' => $attribute->label ?? $attribute->name,
            ];
        }
    }

    public function hasComponent(string $name): bool
    {
        return isset($this->components[$name]);
    }

    public function getComponent(string $name): ?SnippetComponentInterface
    {
        return $this->components[$name]['component'] ?? null;
    }

    public function getTemplate(string $name): ?string
    {
        return $this->components[$name]['template'] ?? null;
    }

    /**
     * Component definitions exposed to the block editor.
     *
     * @return array<string, array{label: string, schema: array<string, array<string, mixed>>}>
     */
    public function getDefinitions(): array
    {
        $definitions = [];
        foreach ($this->components as $name => $component) {
            $definitions[$name] = [
                'label' => $component['label'],
                'schema' => $component['component']->getSchema(),
            ];
        }

        return $definitions;
    }
}
