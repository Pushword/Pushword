<?php

namespace Pushword\Core\Command;

use Pushword\Core\Repository\MediaRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'pw:media:debug', description: 'Debug if a media exists by filename or history')]
final readonly class MediaDebugCommand
{
    public function __construct(
        private MediaRepository $mediaRepository,
    ) {
    }

    public function __invoke(
        #[Argument(description: 'The filename to search for', name: 'search')]
        string $search,
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);

        $media = $this->mediaRepository->findOneByFileName($search);

        if (null === $media) {
            $io->error(\sprintf('No media found for "%s"', $search));

            return 1;
        }

        $foundVia = $media->getFileName() === $search ? 'fileName' : 'fileNameHistory';
        $history = $media->getFileNameHistory();

        $io->success('Media found!');
        $io->table(
            ['Property', 'Value'],
            [
                ['ID', (string) $media->id],
                ['Current name', $media->getFileName()],
                ['Alt', $media->getAlt()],
                ['Found via', $foundVia],
                ['History', [] !== $history ? implode(', ', $history) : '(empty)'],
            ],
        );

        return 0;
    }
}
