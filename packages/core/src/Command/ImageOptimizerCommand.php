<?php

namespace Pushword\Core\Command;

use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\ImageManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pushword:image:optimize', description: 'Optimize all images')]
final readonly class ImageOptimizerCommand
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private ImageManager $imageManager,
    ) {
    }

    public function __invoke(#[Argument(name: 'media', description: 'Image name (eg: filename.jpg).')]
        ?string $mediaName, OutputInterface $output): int
    {
        $medias = null !== $mediaName ? $this->mediaRepository->findBy(['fileName' => $mediaName]) : $this->mediaRepository->findAll();

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
