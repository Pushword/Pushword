<?php

namespace Pushword\Core\Utils\ImageOptimizer;

use Spatie\ImageOptimizer\OptimizerChain;
use Spatie\ImageOptimizer\Optimizers\Cwebp;
use Spatie\ImageOptimizer\Optimizers\Gifsicle;
use Spatie\ImageOptimizer\Optimizers\Optipng;
use Spatie\ImageOptimizer\Optimizers\Pngquant;
use Spatie\ImageOptimizer\Optimizers\Svgo;

class OptimizerChainFactory
{
    /**
     * @param array<string, string> $config
     */
    public static function create(array $config = []): OptimizerChain
    {
        $jpegQuality = '-quality '.($config['quality'] ?? 75);
        $pngQuality = '--quality='.($config['quality'] ?? 85);

        $otpimizerChain = new OptimizerChain();

        $otpimizerChain->addOptimizer(new Mozjpeg([$jpegQuality, '-optimize', '-progressive']));
        $otpimizerChain->addOptimizer(new Pngquant([$pngQuality, '--force']));
        $otpimizerChain->addOptimizer(new Optipng(['-i0', '-o2', '-quiet']));
        $otpimizerChain->addOptimizer(new Svgo(['--disable={cleanupIDs,removeViewBox}']));
        $otpimizerChain->addOptimizer(new Gifsicle(['-b', '-O3']));
        $otpimizerChain->addOptimizer(new Cwebp(['-m 6', '-pass 10', '-mt', '-q 80']));

        return $otpimizerChain;
    }
}
