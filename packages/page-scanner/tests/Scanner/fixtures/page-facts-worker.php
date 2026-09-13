#!/usr/bin/env php
<?php

declare(strict_types=1);

while (false !== ($line = fgets(\STDIN))) {
    $request = json_decode($line, flags: \JSON_THROW_ON_ERROR);
    if (! $request instanceof stdClass || ! isset($request->id, $request->documents) || ! is_array($request->documents)) {
        throw new RuntimeException('Invalid request');
    }
    $mode = trim((string) file_get_contents(__FILE__.'.mode'));
    $documents = array_map(
        static fn (): array => ['hrefs' => ['/one'], 'missing_alt' => ['/lake.jpg'], 'anchors' => ['section']],
        $request->documents,
    );
    if ('missing-field' === $mode) {
        unset($documents[0]['anchors']);
    } elseif ('invalid-list' === $mode) {
        $documents[0]['hrefs'] = ['key' => '/one'];
    } elseif ('invalid-value' === $mode) {
        $documents[0]['missing_alt'] = [123];
    }

    echo json_encode(['version' => 1, 'id' => $request->id, 'documents' => $documents], \JSON_THROW_ON_ERROR)."\n";
}
