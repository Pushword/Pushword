<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\ImageManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pw:image:cache', description: 'Generate all images cache')]
final readonly class ImageManagerCommand
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private EntityManagerInterface $entityManager,
        private ImageManager $imageManager,
    ) {
    }

    public function __invoke(#[Argument(description: 'Image name (eg: filename.jpg).', name: 'media')]
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
                $this->imageManager->generateCache($media);
            }

            $progressBar->advance();
        }

        $this->entityManager->flush(); // permits to update mainColor and dimensions

        $progressBar->finish();

        return 0;
    }
}
