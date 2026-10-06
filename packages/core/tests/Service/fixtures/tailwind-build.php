<?php

declare(strict_types=1);

$projectDir = \dirname((string) getcwd());
if (! @mkdir($projectDir.'/building')) {
    file_put_contents($projectDir.'/overlap', 'Concurrent builds');
    exit(1);
}

$content = [];
foreach (glob($projectDir.'/var/TailwindGeneratorCache/*') ?: [] as $file) {
    $content[basename($file)] = file_get_contents($file);
}

file_put_contents($projectDir.'/builds', json_encode($content, \JSON_THROW_ON_ERROR)."\n", \FILE_APPEND);

if (file_exists($projectDir.'/block-build')) {
    file_put_contents($projectDir.'/build-started', '');
    $deadline = microtime(true) + 10;
    while (! file_exists($projectDir.'/release-build')) {
        clearstatcache();
        if (microtime(true) > $deadline) {
            exit(2);
        }

        usleep(10_000);
    }
}

rmdir($projectDir.'/building');
echo "Build output\n";
fwrite(\STDERR, "Build diagnostic\n");
exit(file_exists($projectDir.'/fail-build') ? 1 : 0);
