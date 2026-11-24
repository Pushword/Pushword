<?php

declare(strict_types=1);

namespace Pushword\Quiz\Twig\Node;

use Override;
use Pushword\Quiz\Service\QuizRenderer;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\CaptureNode;
use Twig\Node\Node;

/**
 * Compiled `{% quiz %}…{% endquiz %}` block. Captures the (Twig-rendered) body
 * as a raw string and hands it to {@see QuizRenderer::render()}. Because the body
 * is template text — not a Twig string literal — its apostrophes need no escaping.
 */
#[YieldReady]
final class QuizNode extends Node
{
    public function __construct(Node $body, int $lineno)
    {
        $capture = new CaptureNode($body, $lineno);
        $capture->setAttribute('raw', true);

        parent::__construct(['capture' => $capture], [], $lineno);
    }

    #[Override]
    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write('$pwQuizJson = ')
            ->subcompile($this->getNode('capture'))
            ->raw("\n")
            ->write('yield $this->env->getRuntime(')
            ->string(QuizRenderer::class)
            ->raw(")->render(\$pwQuizJson);\n");
    }
}
