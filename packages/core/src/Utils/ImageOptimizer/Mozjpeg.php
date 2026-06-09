<?php

namespace Pushword\Core\Utils\ImageOptimizer;

use Override;
use Spatie\ImageOptimizer\Image;
use Spatie\ImageOptimizer\Optimizers\BaseOptimizer;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class Mozjpeg extends BaseOptimizer
{
    public string $binaryName = 'cjpeg';

    public function canHandle(Image $image): bool
    {
        return 'image/jpeg' === $image->mime();
    }

    #[Override]
    public function getCommand(): string
    {
        $command = parent::getCommand();

        // return $command.' > '.escapeshellarg($this->imagePath);

        $process = Process::fromShellCommandline($command);

        $status = $process
            ->setTimeout(60)
            ->run();

        if (0 === $status) {
            new Filesystem()->dumpFile($this->imagePath, $process->getOutput());  // @phpstan-ignore-line
        }

        return 'echo ""';
    }
}
