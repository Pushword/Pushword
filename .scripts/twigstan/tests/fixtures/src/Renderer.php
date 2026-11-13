<?php

declare(strict_types=1);

use Twig\Environment;

final class Renderer
{
    public function parentContext(Environment $twig): string
    {
        return $twig->render('parent/page.twig', ['title' => 'Hello']);
    }

    public function inheritedParentContext(Environment $twig): string
    {
        return $twig->render('parent/leaf.twig', ['title' => 'Hello']);
    }

    public function inheritedArgumentError(Environment $twig): string
    {
        return $twig->render('parent/invalid-argument.twig', ['title' => 'Hello']);
    }

    public function inheritedMissingVariable(Environment $twig): string
    {
        return $twig->render('parent/missing-variable.twig', ['title' => 'Hello']);
    }

    public function nullCoalesce(Environment $twig): string
    {
        return $twig->render('coalesce/null.twig', ['value' => null, 'field' => null]);
    }

    public function nullDefault(Environment $twig): string
    {
        return $twig->render('coalesce/default.twig', ['field' => null]);
    }

    public function nullDefined(Environment $twig): string
    {
        return $twig->render('coalesce/defined.twig', ['field' => null]);
    }

    public function presentValue(Environment $twig): string
    {
        return $twig->render('coalesce/present.twig', ['field' => ['value' => 'present']]);
    }

    public function unguardedNull(Environment $twig): string
    {
        return $twig->render('coalesce/unguarded.twig', ['field' => null]);
    }
}
