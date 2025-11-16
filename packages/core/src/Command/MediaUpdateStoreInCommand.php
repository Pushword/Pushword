<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'pw:media:update-store-in',
    description: 'to use after a migration',
    aliases: []
)]
final readonly class MediaUpdateStoreInCommand
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private EntityManagerInterface $em,
        private string $projectDir,
    ) {
    }

    public function __invoke(
        #[Option(name: 'dry-run', description: 'Display modifications without applying them')]
        bool $dryRun,
        OutputInterface $output
    ): int {
        $newStoreIn = $this->projectDir.'/media';
        $oldStoreIn = $this->mediaRepository->getOldStoreIn();

        if (null === $oldStoreIn || $oldStoreIn === $newStoreIn) {
            return Command::SUCCESS;
        }

        /** @var Media[] $medias */
        $medias = $this->mediaRepository->findAll();

        $output->writeln(sprintf('<info>%d media found to update.</info>', \count($medias)));

        $progressBar = new ProgressBar($output, \count($medias));
        $progressBar->setMessage('');
        $progressBar->setFormat("%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% \r\n %message%");
        $progressBar->start();

        $updated = 0;
        foreach ($medias as $media) {
            $progressBar->setMessage(sprintf('Updating: %s', $media->getMedia()));
            $media->setStoreIn($newStoreIn);
            $this->testMediaExists($media, $dryRun, $output);
            ++$updated;

            $progressBar->advance();
        }

        $progressBar->finish();
        $output->writeln('');

        if (! $dryRun) {
            $this->em->flush();
            $output->writeln(sprintf('<info>%d media updated successfully.</info>', $updated));
        } else {
            $output->writeln(sprintf('<info>%d media would be updated (dry-run).</info>', $updated));
        }

        return Command::SUCCESS;
    }

    private function testMediaExists(Media $media, bool $dryRun, OutputInterface $output): void
    {
        if (file_exists($media->getPath())) {
            return;
        }

        if ($dryRun) {
            $output->writeln(sprintf('<error>Media `%s` was not found.</error>', $media->getMedia()));

            return;
        }

        throw new Exception(sprintf('Media `%s` was not found.', $media->getMedia()));
    }
}
