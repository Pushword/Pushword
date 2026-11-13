<?php

declare(strict_types=1);

require __DIR__.'/autoload.php';

$config = require __DIR__.'/ci.php';
$kernel = require __DIR__.'/twig-loader.php';
$loader = $kernel->getContainer()->get('test.service_container')->get('twig')->getLoader();
$result = json_decode(file_get_contents($config->tempDirectory.'/phpstan/collect-php-render-points.result.json'), true, flags: \JSON_THROW_ON_ERROR);
$rendered = [];
foreach ($result['collectedData'] as $collected) {
    foreach ($collected['data'] as $group) {
        foreach ($group as $render) {
            $path = $loader->getSourceContext($render['template'])->getPath();
            $rendered[realpath($path)] = true;
        }
    }
}

$covered = 0;
$missing = [];
foreach ($config->twigPaths as $template) {
    if (isset($rendered[realpath($template)])) {
        ++$covered;
    } else {
        $missing[] = $template;
    }
}

echo json_encode(['tool' => 'twigstan-ci-coverage', 'covered' => $covered, 'missing' => $missing], \JSON_THROW_ON_ERROR)."\n";
exit([] === $missing ? 0 : 1);
