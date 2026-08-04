<?php

declare(strict_types=1);

use TwigCsFixer\File\Finder;
use TwigCsFixer\Config\Config;
use TwigCsFixer\Rules\Function\IncludeFunctionRule;

$finder = new Finder()
    ->in(__DIR__.'/packages/*/src/templates')
    ->in(__DIR__.'/packages/*/templates')
    ->in(__DIR__.'/packages/dev-app/templates')
    // Web-server configs, not markup: their tab indentation is the convention of the
    // file they generate, and the HTML ruleset would rewrite it to spaces.
    ->notName('Caddyfile.twig')
    ->notName('htaccess.twig');

$config = new Config()->setFinder($finder);

// Rewriting `{% include %}` into `{{ include() }}` is a style preference, not a
// deprecation. Our templates are overridable by downstream sites, so leave the tag
// form the theme docs use alone.
$config->getRuleset()->removeRule(IncludeFunctionRule::class);

return $config;
