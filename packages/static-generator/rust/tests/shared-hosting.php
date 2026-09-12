<?php

declare(strict_types=1);

use Pushword\StaticGenerator\Generator\HtmlMinification;
use Pushword\StaticGenerator\Generator\HtmlMinifier;

require __DIR__.'/../../../../vendor/autoload.php';

if (\function_exists('proc_open')) {
    throw new RuntimeException('Run with -d disable_functions=proc_open');
}

$html = '<!DOCTYPE html><html><body><p>à   b</p><!-- removed --></body></html>';
foreach ([null, \PHP_BINARY] as $binary) {
    if (HtmlMinifier::compress($html) !== new HtmlMinification($binary)->compress($html)) {
        throw new RuntimeException('PHP-only hosting must preserve the PHP output');
    }
}

echo "PHP-only hosting passed with proc_open disabled.\n";
