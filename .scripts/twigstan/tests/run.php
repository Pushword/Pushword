<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

$tool = \dirname(__DIR__);
require $tool.'/vendor/autoload.php';
require __DIR__.'/fixtures/src/Renderer.php';

$twig = require __DIR__.'/fixtures/twig-loader.php';
$renderer = new Renderer();
$directory = sys_get_temp_dir().'/pw-twigstan-patches-'.uniqid();
$cases = [
    'parent context' => ['parentContext', 'parent/page.twig', null, 'DemoDemoInterDemoHello'],
    'inherited parent context' => ['inheritedParentContext', 'parent/leaf.twig', null, 'DemoDemoInterDemoHello'],
    'inherited argument error' => ['inheritedArgumentError', 'parent/invalid-argument.twig', 'argument.type', null],
    'inherited missing variable' => ['inheritedMissingVariable', 'parent/missing-variable.twig', 'offsetAccess.notFound', null],
    'null coalesce' => ['nullCoalesce', 'coalesce/null.twig', null, 'fallback'],
    'null default' => ['nullDefault', 'coalesce/default.twig', null, 'fallback'],
    'null defined' => ['nullDefined', 'coalesce/defined.twig', null, 'fallback'],
    'present value' => ['presentValue', 'coalesce/present.twig', null, 'present'],
    'unguarded null' => ['unguardedNull', 'coalesce/unguarded.twig', 'getAttribute.null', null],
];
$results = [];
$passed = 0;
$failed = 0;

try {
    foreach ($cases as $name => [$method, $template, $identifier, $expectedOutput]) {
        $process = new Process([
            \PHP_BINARY,
            $tool.'/vendor/bin/twigstan',
            'analyze',
            '--configuration='.__DIR__.'/fixtures/twigstan.php',
            '--no-ansi',
            __DIR__.'/fixtures/templates/'.$template,
        ], __DIR__.'/fixtures', ['PUSHWORD_TWIGSTAN_TEST_CACHE' => $directory.'/'.$method, 'PAO_DISABLE' => '1'], timeout: 60);

        try {
            $exit = $process->run();
            $output = $process->getOutput().$process->getErrorOutput();
            $diagnostics = preg_match_all('/^🔖 /m', $output);

            if (null === $identifier) {
                $rendered = preg_replace('/\s+/', '', $renderer->$method($twig));
                $success = 0 === $exit && 0 === $diagnostics && str_contains($output, 'No errors found') && $expectedOutput === $rendered;
            } else {
                $success = 1 === $exit && 1 === $diagnostics && str_contains($output, '🔖 '.$identifier);
            }

            if (! $success) {
                fwrite(\STDERR, $name.":\n".$output."\n");
            }
        } catch (Throwable $exception) {
            $success = false;
            fwrite(\STDERR, $name.': '.$exception->getMessage()."\n");
        }

        $results[] = ['case' => $name, 'passed' => $success];
        if ($success) {
            ++$passed;
        } else {
            ++$failed;
        }
    }
} finally {
    new Filesystem()->remove($directory);
}

echo json_encode(['tool' => 'twigstan-patches', 'passed' => $passed, 'failed' => $failed, 'cases' => $results], \JSON_THROW_ON_ERROR)."\n";
exit(0 === $failed ? 0 : 1);
