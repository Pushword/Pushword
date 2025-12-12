<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\PdfOptimizer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'pw:pdf:optimize', description: 'Optimize PDF files (compress and linearize)')]
final readonly class PdfOptimizerCommand
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private EntityManagerInterface $entityManager,
        private PdfOptimizer $pdfOptimizer,
    ) {
    }

    public function __invoke(
        #[Argument(description: 'PDF filename (eg: document.pdf).', name: 'media')]
        ?string $mediaName,
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Force re-optimization', name: 'force', shortcut: 'f')]
        bool $force = false,
    ): int {
        $io = new SymfonyStyle($input, $output);

        if (! $this->pdfOptimizer->isAvailable()) {
            $io->warning('PDF optimization not available. Install ghostscript (gs) and/or qpdf.');
            $io->listing([
                'Ghostscript: '.($this->pdfOptimizer->isGhostscriptAvailable() ? 'available' : 'not found'),
                'qpdf: '.($this->pdfOptimizer->isQpdfAvailable() ? 'available' : 'not found'),
            ]);

            return 1;
        }

        $medias = null !== $mediaName
            ? $this->mediaRepository->findBy(['fileName' => $mediaName])
            : $this->mediaRepository->findBy(['mimeType' => 'application/pdf']);

        if ([] === $medias) {
            $io->info(null !== $mediaName ? 'PDF not found: '.$mediaName : 'No PDF files to optimize.');

            return 0;
        }

        $progressBar = new ProgressBar($output, \count($medias));
        $progressBar->setMessage('');
        $progressBar->setFormat("%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% \r\n %message%");
        $progressBar->start();

        $errors = [];
        $optimized = 0;
        $skipped = 0;

        foreach ($medias as $media) {
            $progressBar->setMessage($media->getFileName());

            try {
                $result = $this->pdfOptimizer->optimize($media, $force);
                if ($result) {
                    ++$optimized;
                } else {
                    ++$skipped;
                }
            } catch (Throwable $exception) {
                $errors[] = $media->getFileName().': '.$exception->getMessage();
            }

            $progressBar->advance();
        }

        $this->entityManager->flush();
        $progressBar->finish();

        $io->newLine(2);

        if ($optimized > 0) {
            $io->success(\sprintf('%d PDF(s) optimized successfully', $optimized));
        }

        if ($skipped > 0) {
            $io->info(\sprintf('%d PDF(s) skipped (already optimal or tools unavailable)', $skipped));
        }

        if ([] !== $errors) {
            $io->warning('Some PDFs failed to process:');
            $io->listing($errors);
        }

        return 0;
    }
}
