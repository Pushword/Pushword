<?php

/*
 * Folds the --coverage-php dumps written by .scripts/test-coverage's three
 * batches into a single HTML + clover report.
 *
 * PHPUnit merges coverage only within one run, and the batches have to stay
 * separate runs for isolation reasons, so the merge happens here.
 *
 * Usage: merge-coverage.php <html-dir> <clover-file> <dump.cov>...
 */

use SebastianBergmann\CodeCoverage\Node\Builder;
use SebastianBergmann\CodeCoverage\Report\Clover;
use SebastianBergmann\CodeCoverage\Report\Html\Facade as HtmlReport;
use SebastianBergmann\CodeCoverage\Report\Text;
use SebastianBergmann\CodeCoverage\Report\Thresholds;
use SebastianBergmann\CodeCoverage\Serialization\Merger;
use SebastianBergmann\CodeCoverage\StaticAnalysis\Registry;

require __DIR__.'/../vendor/autoload.php';

[, $htmlDir, $cloverFile] = $argv;
$dumps = \array_slice($argv, 3);

$merged = (new Merger())->merge($dumps);
$report = (new Builder(Registry::analyser(null, false, false)))
    ->build($merged['codeCoverage'], $merged['testResults'], $merged['basePath']);

(new HtmlReport())->process($report, $htmlDir);
(new Clover())->process($report, $cloverFile);

echo (new Text(Thresholds::default(), false, true))->process($report, true);
