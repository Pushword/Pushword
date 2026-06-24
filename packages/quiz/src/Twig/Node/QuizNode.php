<?php

namespace Pushword\Quiz\Twig\Node;

use Override;
use Pushword\Quiz\Service\QuizRenderer;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Extension\CoreExtension;
use Twig\Node\Node;

/**
 * Compiled `{% quiz %}…{% endquiz %}` block. Captures the (Twig-rendered) body
 * as a raw string and hands it to {@see QuizRenderer::render()}. Because the body
 * is template text — not a Twig string literal — its apostrophes need no escaping.
 *
 * The capture mirrors {@see \Twig\Node\CaptureNode} (`raw` flavour) so it works
 * under Twig's yield-based output as well as the legacy buffered output.
 */
#[YieldReady]
final class QuizNode extends Node
{
    public function __construct(Node $body, int $lineno)
    {
        parent::__construct(['body' => $body], [], $lineno);
    }

    #[Override]
    public function compile(Compiler $compiler): void
    {
        $useYield = $compiler->getEnvironment()->useYield();

        $compiler
            ->addDebugInfo($this)
            ->write('$pwQuizJson = ')
            ->raw($useYield ? "implode('', iterator_to_array(" : CoreExtension::class.'::captureOutput(')
            ->raw("(function () use (&\$context, \$macros, \$blocks) {\n")
            ->indent()
            ->subcompile($this->getNode('body'))
            ->write("yield from [];\n")
            ->outdent()
            ->write('})()')
            ->raw($useYield ? ', false))' : ')')
            ->raw(";\n")
            ->write('yield $this->env->getRuntime(\\'.QuizRenderer::class.'::class)->render($pwQuizJson);'."\n");
    }
}
