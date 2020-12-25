<?php

namespace Pushword\Core\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pushword:image:optimize')]
final class ImageOptimizerCommand extends Command
{
    use ImageCommandTrait;

    protected function configure(): void
    {
        $this->setDescription('Generate all images cache')
            ->addArgument('media', InputArgument::OPTIONAL, 'Image name (eg: filename.jpg).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $medias = $this->getMedias($input);

        $progressBar = new ProgressBar($output, \count($medias));
        $progressBar->setMessage('');
        $progressBar->setFormat("%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% \r\n %message%");
        $progressBar->start();
        foreach ($medias as $media) {
            if ($this->imageManager->isImage($media)) {
                $progressBar->setMessage($media->getPath());
                $this->imageManager->optimize($media);
            }

            $progressBar->advance();
        }

        $progressBar->finish();

        return 0;
    }
}
