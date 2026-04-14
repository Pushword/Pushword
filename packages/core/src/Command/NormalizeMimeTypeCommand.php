<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pw:media:normalize-mime-type', description: 'Normalize legacy image/jpg MIME types to image/jpeg')]
final readonly class NormalizeMimeTypeCommand
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
    ): int {
        // Find all media with image/jpg MIME type
        $queryBuilder = $this->mediaRepository->createQueryBuilder('m');
        $queryBuilder->where('m.mimeType = :oldMimeType');
        $queryBuilder->setParameter('oldMimeType', 'image/jpg');

        $medias = $queryBuilder->getQuery()->getResult();
        $count = \count($medias);

        if (0 === $count) {
            $output->writeln('<info>No media with image/jpg MIME type found. Database is already normalized.</info>');

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Normalizing %d media(s) from image/jpg to image/jpeg...</info>', $count));

        $progressBar = new ProgressBar($output, $count);
        $progressBar->setFormat("%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% \r\n %message%");
        $progressBar->start();

        foreach ($medias as $media) {
            $progressBar->setMessage('Updating: '.$media->getFileName());
            $media->setMimeType('image/jpeg');
            $progressBar->advance();
        }

        $this->em->flush();

        $progressBar->setMessage('<info>Normalization completed successfully!</info>');
        $progressBar->finish();

        $output->writeln('');
        $output->writeln(sprintf('<info>Successfully normalized %d media(s).</info>', $count));

        return Command::SUCCESS;
    }
}
