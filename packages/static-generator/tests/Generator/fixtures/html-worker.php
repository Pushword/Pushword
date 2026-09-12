#!/usr/bin/env php
<?php

declare(strict_types=1);

while (false !== $line = fgets(\STDIN)) {
    $request = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
    if (! is_array($request) || ! isset($request['id']) || ! is_int($request['id'])
        || ! isset($request['documents']) || ! is_array($request['documents'])) {
        exit(2);
    }
    $mode = file_get_contents(__FILE__.'.mode');
    if ('crash' === $mode) {
        exit(7);
    }
    if ('timeout' === $mode) {
        usleep(1000000);
    }
    if ('json' === $mode || 'incomplete' === $mode) {
        echo 'incomplete' === $mode ? '{' : "invalid\n";
        exit;
    }
    if ('overflow' === $mode || 'stderr' === $mode) {
        fwrite('stderr' === $mode ? \STDERR : \STDOUT, str_repeat('x', 16 * 1024 * 1024 + 1));
        exit;
    }
    echo json_encode([
        'version' => 'version' === $mode ? 2 : 1,
        'id' => 'id' === $mode ? $request['id'] - 1 : $request['id'],
        'documents' => match ($mode) {
            'count' => [],
            'type' => [null, []],
            'object' => (object) ['0' => 'first', '1' => 'second'],
            default => array_map(static function (mixed $html): string {
                if (! is_string($html)) {
                    throw new RuntimeException('Expected HTML from the adapter');
                }

                return 'native:'.getmypid().':'.$html;
            }, $request['documents']),
        },
    ], \JSON_THROW_ON_ERROR)."\n";
    fflush(\STDOUT);
}
