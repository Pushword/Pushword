#!/usr/bin/env php
<?php

declare(strict_types=1);

while (false !== $line = fgets(\STDIN)) {
    $request = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
    if (! is_array($request) || ! is_array($request['documents'] ?? null)) {
        exit(2);
    }
    $payload = json_decode((string) file_get_contents(__FILE__.'.response'), flags: \JSON_THROW_ON_ERROR);
    file_put_contents(__FILE__.'.calls', "call\n", \FILE_APPEND);
    $documents = $payload instanceof stdClass && isset($payload->batch) ? $payload->batch : array_fill(0, count($request['documents']), $payload);
    echo json_encode(['version' => 1, 'id' => $request['id'], 'documents' => $documents], \JSON_THROW_ON_ERROR)."\n";
    fflush(\STDOUT);
}
