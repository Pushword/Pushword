<?php

namespace Pushword\Core\Utils\ImageOptimizer;

use Spatie\ImageOptimizer\Image;
use Spatie\ImageOptimizer\Optimizers\BaseOptimizer;
use Symfony\Component\Process\Process;

class Mozjpeg extends BaseOptimizer
{
    public string $binaryName = 'cjpeg';

    public function canHandle(Image $image): bool
    {
        return 'image/jpeg' === $image->mime();
    }

    public function getCommand(): string
    {
        $command = parent::getCommand();

        //return $command.' > '.escapeshellarg($this->imagePath);

        $process = Process::fromShellCommandline($command);

        $status = $process
            ->setTimeout(60)
            ->run();

        if (0 === $status) {
            \Safe\file_put_contents($this->imagePath, $process->getOutput());
        }

        return 'echo ""';
    }
}
