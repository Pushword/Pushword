<?php

declare(strict_types=1);

namespace Pushword\Core\TwigStan;

use Override;
use Pushword\Core\Site\SiteConfig;
use Twig\Environment;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/** Resolves literal view() calls in the Twig environment used for analysis. */
final readonly class ResolveViewNodeVisitor implements NodeVisitorInterface
{
    public function __construct(private SiteConfig $site)
    {
    }

    #[Override]
    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    #[Override]
    public function leaveNode(Node $node, Environment $env): Node
    {
        if (! $node instanceof FunctionExpression || 'view' !== $node->getAttribute('name')) {
            return $node;
        }

        $arguments = [];
        foreach ($node->getNode('arguments') as $key => $argument) {
            $name = match ($key) {
                0, 'path' => 'path',
                1, 'fallback' => 'fallback',
                default => null,
            };

            // Keep invalid calls intact so the analyzer can report their arguments.
            if (null === $name || \array_key_exists($name, $arguments) || ! $argument instanceof ConstantExpression) {
                return $node;
            }

            $arguments[$name] = $argument->getAttribute('value');
        }

        $path = $arguments['path'] ?? null;
        $fallback = $arguments['fallback'] ?? '@Pushword';
        if (! \is_string($path) || ! \is_string($fallback)) {
            return $node;
        }

        return new ConstantExpression($this->site->getView($path, $fallback), $node->getTemplateLine());
    }

    #[Override]
    public function getPriority(): int
    {
        return -10;
    }
}
