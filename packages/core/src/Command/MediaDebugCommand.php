<?php

namespace Pushword\Core\Command;

use Pushword\Core\Repository\MediaRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
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
        #[Argument(description: 'The filename(s) to search for (one per line)', name: 'search')]
        string $search,
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Output as JSON map')]
        bool $json = false,
    ): int {
        $searchTerms = array_filter(explode("\n", $search), static fn (string $term): bool => '' !== trim($term));

        if ($json) {
            return $this->outputJson($searchTerms, $output);
        }

        return $this->outputTable($searchTerms, new SymfonyStyle($input, $output));
    }

    /**
     * @param string[] $searchTerms
     */
    private function outputJson(array $searchTerms, OutputInterface $output): int
    {
        $results = [];
        foreach ($searchTerms as $term) {
            $media = $this->mediaRepository->findOneByFileName($term);
            $results[$term] = $media?->getFileName();
        }

        $output->writeln(json_encode($results, \JSON_THROW_ON_ERROR));

        return 0;
    }

    /**
     * @param string[] $searchTerms
     */
    private function outputTable(array $searchTerms, SymfonyStyle $io): int
    {
        $hasNotFound = false;

        foreach ($searchTerms as $term) {
            $media = $this->mediaRepository->findOneByFileName($term);

            if (null === $media) {
                $io->error(\sprintf('No media found for "%s"', $term));
                $hasNotFound = true;

                continue;
            }

            $foundVia = $media->getFileName() === $term ? 'fileName' : 'fileNameHistory';
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
        }

        return $hasNotFound ? 1 : 0;
    }
}
