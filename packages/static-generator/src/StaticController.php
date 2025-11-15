<?php

namespace Pushword\StaticGenerator;

use function Safe\file_get_contents;
use function Safe\file_put_contents;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AutoconfigureTag('controller.service_arguments')]
class StaticController extends AbstractController
{
    private const PID_FILE = 'static-generator.pid';

    private const OUTPUT_FILE = 'static-generator-output.txt';

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $varDir
    ) {
    }

    private function getStaticDir(): string
    {
        $staticDir = $this->varDir.'/static';
        if (! $this->filesystem->exists($staticDir)) {
            $this->filesystem->mkdir($staticDir);
        }

        return $staticDir;
    }

    #[Route(path: '/~static', name: 'old_piedweb_static_generate', methods: ['GET'], priority: 1)]
    public function redirectOldStaticRoute(): RedirectResponse
    {
        return $this->redirectToRoute('piedweb_static_generate', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(path: '/{host}', name: 'piedweb_static_generate', methods: ['GET'], priority: -1)]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function generateStatic(?string $host = null): Response
    {
        $staticDir = $this->getStaticDir();
        $pidFile = $staticDir.'/'.self::PID_FILE;
        $outputFile = $staticDir.'/'.self::OUTPUT_FILE;

        // Vérifier si un processus est déjà en cours
        $isRunning = $this->isProcessRunning($pidFile);

        if ($isRunning) {
            // Récupérer la date de début depuis le fichier PID
            $startTime = null;
            if ($this->filesystem->exists($pidFile)) {
                $pidData = json_decode(file_get_contents($pidFile), true);
                if (is_array($pidData) && isset($pidData['startTime']) && is_numeric($pidData['startTime'])) {
                    $startTime = (int) $pidData['startTime'];
                } else {
                    // Fallback: utiliser le timestamp de modification du fichier
                    $startTime = (int) filemtime($pidFile);
                }
            }

            // Afficher la page "is running" avec meta refresh
            return $this->render('@PushwordStatic/running.html.twig', [
                'host' => $host,
                'startTime' => $startTime,
            ]);
        }

        // Vérifier si on doit lancer un nouveau processus ou afficher les résultats
        if ($this->filesystem->exists($outputFile)) {
            // Le processus est terminé, récupérer les résultats
            $output = file_get_contents($outputFile);
            $this->filesystem->remove($outputFile);

            $errors = [];
            // Parser la sortie pour extraire les erreurs
            if ('' !== $output) {
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ('' !== $line && (str_contains(strtolower($line), 'error') || str_contains(strtolower($line), 'failed'))) {
                        $errors[] = $line;
                    }
                }
            }

            // Nettoyer le fichier PID
            if ($this->filesystem->exists($pidFile)) {
                $this->filesystem->remove($pidFile);
            }

            return $this->render('@PushwordStatic/results.html.twig', [
                'errors' => $errors,
                'output' => $output,
            ]);
        }

        // Lancer le processus en arrière-plan avec Process
        $projectDir = $this->getParameter('kernel.project_dir');
        $commandParts = ['php', 'bin/console', 'pushword:static:generate'];
        if (null !== $host) {
            $commandParts[] = $host;
        }

        // Construire la commande shell avec redirection et détachement
        // Utiliser sh -c pour s'assurer que $! fonctionne correctement
        $commandLine = implode(' ', array_map('escapeshellarg', $commandParts));
        $command = sprintf(
            'cd %s && sh -c "%s >> %s 2>&1 & echo \\$!"',
            escapeshellarg($projectDir),
            $commandLine,
            escapeshellarg($outputFile)
        );

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(null);
        $process->mustRun();

        // Récupérer le PID depuis la sortie
        $pidOutput = trim($process->getOutput());
        $pid = (int) $pidOutput;

        if ($pid > 0) {
            // Stocker le PID et le timestamp de début
            $pidData = [
                'pid' => $pid,
                'startTime' => time(),
            ];
            file_put_contents($pidFile, json_encode($pidData));
        } else {
            throw new \RuntimeException('Impossible de lancer le processus en arrière-plan');
        }

        // Rediriger vers la page "is running"
        return $this->redirectToRoute('piedweb_static_generate', ['host' => $host]);
    }

    private function isProcessRunning(string $pidFile): bool
    {
        if (! $this->filesystem->exists($pidFile)) {
            return false;
        }

        $pidData = json_decode(file_get_contents($pidFile), true);
        $pid = null;

        if (is_array($pidData) && isset($pidData['pid']) && is_numeric($pidData['pid'])) {
            $pid = (int) $pidData['pid'];
        } else {
            // Fallback: lire comme un simple entier (ancien format)
            $pid = (int) trim(file_get_contents($pidFile));
        }

        if ($pid <= 0) {
            return false;
        }

        // Vérifier si le processus existe encore
        return $this->filesystem->exists('/proc/'.$pid);
    }
}
