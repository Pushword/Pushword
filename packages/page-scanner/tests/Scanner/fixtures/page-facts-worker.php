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
        static fn (): array => [
            'hrefs' => ['/one'],
            'missing_alt' => ['/lake.jpg'],
            'anchors' => ['section'],
            'linked_attributes' => [['name' => 'href', 'value' => '/one']],
            'srcsets' => ['/lake.jpg 1x'],
        ],
        $request->documents,
    );
    if ('missing-field' === $mode) {
        unset($documents[0]['anchors']);
    } elseif ('invalid-list' === $mode) {
        $documents[0]['hrefs'] = ['key' => '/one'];
    } elseif ('invalid-value' === $mode) {
        $documents[0]['missing_alt'] = [123];
    } elseif ('invalid-attribute' === $mode) {
        $documents[0]['linked_attributes'] = [['name' => 123, 'value' => '/one']];
    } elseif ('invalid-attribute-list' === $mode) {
        $documents[0]['linked_attributes'] = ['key' => ['name' => 'href', 'value' => '/one']];
    }

    echo json_encode(['version' => 1, 'id' => $request->id, 'documents' => $documents], \JSON_THROW_ON_ERROR)."\n";
}
