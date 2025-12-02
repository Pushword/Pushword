<?php

namespace Pushword\AdminBlockEditor\Command;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\AdminBlockEditor\EditorJsHelper;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'pw:json-to-markdown', description: 'Convert EditorJS JSON content to Markdown for all pages.')]
final readonly class ConvertJsonToMarkdownCommand
{
    public function __construct(private PageRepository $pageRepo, private EntityManagerInterface $em)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Afficher les pages à convertir sans les modifier')]
        bool $dryRun = false,
        #[Option(description: 'Filtrer par host (domaine)')]
        ?string $host = null,
        #[Option(description: 'Convertir seulement une page spécifique (par ID)')]
        ?string $pageId = null,
    ): int {
        $hostFilter = $host;
        $pageIdFilter = $pageId;
        $io->title('Conversion JSON EditorJS vers Markdown');
        // Récupérer les pages à convertir
        $pages = $this->getPages($hostFilter, $pageIdFilter);
        if ([] === $pages) {
            $io->warning('Aucune page trouvée.');

            return Command::SUCCESS;
        }

        $io->text(\sprintf('Total de pages à analyser : %d', \count($pages)));
        // Compter les pages JSON
        $jsonPages = [];
        foreach ($pages as $page) {
            if ($this->isPageInJsonFormat($page)) {
                $jsonPages[] = $page;
            }
        }

        if ([] === $jsonPages) {
            $io->success('Aucune page en format JSON trouvée. Toutes les pages sont déjà en Markdown !');

            return Command::SUCCESS;
        }

        $io->text(\sprintf('Pages en format JSON à convertir : %d', \count($jsonPages)));
        if ($dryRun) {
            $io->section('Pages qui seraient converties (dry-run) :');
            foreach ($jsonPages as $page) {
                $io->text(\sprintf('  - [%d] https://%s/%s', $page->getId(), $page->getHost(), $page->getSlug()));
            }

            return Command::SUCCESS;
        }

        // Convertir les pages
        $io->progressStart(\count($jsonPages));
        $converted = 0;
        $errors = 0;
        foreach ($jsonPages as $page) {
            try {
                $this->convertPage($page, $io);
                ++$converted;
            } catch (Exception $e) {
                ++$errors;
                $io->error(\sprintf(
                    'Erreur lors de la conversion de la page [%d] %s/%s : %s',
                    $page->getId(),
                    $page->getHost(),
                    $page->getSlug(),
                    $e->getMessage()
                ));
            }

            $io->progressAdvance();
        }

        $this->em->flush();
        $io->progressFinish();
        // Afficher le résumé
        $io->success(\sprintf(
            'Conversion terminée : %d page(s) convertie(s), %d erreur(s).',
            $converted,
            $errors
        ));

        return Command::SUCCESS;
    }

    /**
     * Récupère les pages à analyser.
     *
     * @return array<Page>
     */
    private function getPages(?string $hostFilter, ?string $pageIdFilter): array
    {
        if (null !== $pageIdFilter) {
            $page = $this->pageRepo->find((int) $pageIdFilter);

            return null !== $page ? [$page] : [];
        }

        if (null !== $hostFilter) {
            return $this->pageRepo->findBy(['host' => $hostFilter]);
        }

        return $this->pageRepo->findAll();
    }

    /**
     * Vérifie si une page est en format JSON EditorJS.
     */
    private function isPageInJsonFormat(Page $page): bool
    {
        $mainContent = $page->getMainContent();

        return false !== EditorJsHelper::tryToDecode($mainContent);
    }

    /**
     * Convertit une page JSON en Markdown.
     */
    private function convertPage(Page $page, SymfonyStyle $io): void
    {
        $jsonContent = $page->getMainContent();

        // Appeler le script Node.js pour la conversion
        $markdown = $this->convertJsonToMarkdown($jsonContent);

        // Mettre à jour la page
        $page->setMainContent(trim($markdown));

        if ($io->isVerbose()) {
            $io->text(\sprintf(
                '✓ Page [%d] %s/%s convertie',
                $page->getId(),
                $page->getHost(),
                $page->getSlug()
            ));
        }
    }

    /**
     * Appelle le script Node.js pour convertir le JSON en Markdown.
     */
    private function convertJsonToMarkdown(string $jsonContent): string
    {
        // Chemin vers le script Node.js (relatif à ce fichier)
        $scriptPath = \dirname(__DIR__).'/Command/convert-json-to-markdown-built/convert-json-to-markdown.mjs';

        if (! file_exists($scriptPath)) {
            throw new RuntimeException(\sprintf("Le script de conversion n'existe pas : %s", $scriptPath));
        }

        // Créer un fichier temporaire pour le localStorage (requis par Node.js v25+)
        $localStoragePath = sys_get_temp_dir().'/pushword-localstorage-'.bin2hex(random_bytes(8)).'.json';

        // Créer le processus avec le flag --localstorage-file
        $process = new Process(['node', '--localstorage-file='.$localStoragePath, $scriptPath]);
        $process->setInput($jsonContent);
        $process->setTimeout(60); // Timeout de 60 secondes

        try {
            $process->mustRun();

            return trim($process->getOutput());
        } catch (ProcessFailedException $processFailedException) {
            throw new RuntimeException(\sprintf('Erreur lors de la conversion JSON vers Markdown : %s', $processFailedException->getMessage()), 0, $processFailedException);
        } finally {
            // Nettoyer le fichier temporaire
            if (file_exists($localStoragePath)) {
                @unlink($localStoragePath);
            }
        }
    }
}
