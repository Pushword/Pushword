<?php

/*
 * Emits shields.io endpoint JSON for the line coverage of a clover report.
 * CI pushes the output to the `badges` branch; the README badge reads it via
 * https://img.shields.io/endpoint — no coverage service or token involved.
 *
 * Usage: coverage-badge.php <clover-file>
 */

[, $cloverFile] = $argv;

$metrics = (new SimpleXMLElement(file_get_contents($cloverFile)))->project->metrics;
$percent = round(100 * (int) $metrics['coveredstatements'] / max(1, (int) $metrics['statements']), 1);

echo json_encode([
    'schemaVersion' => 1,
    'label' => 'coverage',
    'message' => $percent.'%',
    'color' => $percent >= 90 ? 'brightgreen' : ($percent >= 80 ? 'green' : ($percent >= 60 ? 'yellow' : 'red')),
]);
