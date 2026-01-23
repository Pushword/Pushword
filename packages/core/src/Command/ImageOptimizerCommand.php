<?php

namespace Pushword\Core\Command;

use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\ImageManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'pw:image:optimize', description: 'Optimize all images')]
final readonly class ImageOptimizerCommand
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private ImageManager $imageManager,
    ) {
    }

    public function __invoke(
        #[Argument(description: 'Image name (eg: filename.jpg).', name: 'media')]
        ?string $mediaName,
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $medias = null !== $mediaName ? $this->mediaRepository->findBy(['fileName' => $mediaName]) : $this->mediaRepository->findAll();

        $progressBar = new ProgressBar($output, \count($medias));
        $progressBar->setMessage('');
        $progressBar->setFormat("%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% \r\n %message%");
        $progressBar->start();

        $errors = [];
        foreach ($medias as $media) {
            if ($this->imageManager->isImage($media)) {
                $progressBar->setMessage($media->getPath());

                try {
                    $this->imageManager->optimize($media);
                } catch (Throwable $exception) {
                    $errors[] = $media->getFileName().': '.$exception->getMessage();
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();

        if ([] !== $errors) {
            $io->warning('Some images failed to optimize:');
            $io->listing($errors);
        }

        return 0;
    }
}
