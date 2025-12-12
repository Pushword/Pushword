<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\ImageManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'pw:image:cache', description: 'Generate all images cache')]
final readonly class ImageManagerCommand
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private EntityManagerInterface $entityManager,
        private ImageManager $imageManager,
    ) {
    }

    public function __invoke(
        #[Argument(description: 'Image name (eg: filename.jpg).', name: 'media')]
        ?string $mediaName,
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Force regeneration even if cache is fresh', name: 'force', shortcut: 'f')]
        bool $force = false,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $medias = null !== $mediaName ? $this->mediaRepository->findBy(['fileName' => $mediaName]) : $this->mediaRepository->findAll();

        $progressBar = new ProgressBar($output, \count($medias));
        $progressBar->setMessage('');
        $progressBar->setFormat("%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% \r\n %message%");
        $progressBar->start();

        $errors = [];
        $skipped = 0;

        foreach ($medias as $media) {
            if ($this->imageManager->isImage($media)) {
                $progressBar->setMessage($media->getPath());

                try {
                    $generated = $this->imageManager->generateCache($media, $force);
                    if (! $generated) {
                        ++$skipped;
                    }
                } catch (Throwable $exception) {
                    $errors[] = $media->getFileName().': '.$exception->getMessage();
                }
            }

            $progressBar->advance();
        }

        $this->entityManager->flush();
        $progressBar->finish();

        if ($skipped > 0) {
            $io->info(\sprintf('%d image(s) skipped (cache already fresh)', $skipped));
        }

        if ([] !== $errors) {
            $io->warning('Some images failed to process:');
            $io->listing($errors);
        }

        return 0;
    }
}
