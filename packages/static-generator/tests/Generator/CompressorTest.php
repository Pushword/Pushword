<?php

namespace Pushword\StaticGenerator\Tests\Generator;

use PHPUnit\Framework\TestCase;
use Pushword\StaticGenerator\Generator\CompressionAlgorithm;
use Pushword\StaticGenerator\Generator\Compressor;
use Symfony\Component\Filesystem\Filesystem;

class CompressorTest extends TestCase
{
    private string $tempDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir().'/pushword-compressor-test-'.uniqid();
        $this->filesystem->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if ($this->filesystem->exists($this->tempDir)) {
            $this->filesystem->remove($this->tempDir);
        }
    }

    public function testConstructorDetectsAvailableCompressors(): void
    {
        $compressor = new Compressor();

        // Vérifie que seuls les compresseurs valides sont détectés
        foreach ($compressor->availableCompressors as $algorithm) {
            self::assertContains($algorithm, CompressionAlgorithm::cases());
        }
    }

    public function testCompressWithUnavailableAlgorithmDoesNothing(): void
    {
        $compressor = new Compressor();

        // Créer un fichier de test
        $testFile = $this->tempDir.'/test.txt';
        $this->filesystem->dumpFile($testFile, 'Test content');

        // Find an algorithm that is not available on this system
        $unavailableAlgorithm = null;
        foreach (CompressionAlgorithm::cases() as $algorithm) {
            if (! \in_array($algorithm, $compressor->availableCompressors, true)) {
                $unavailableAlgorithm = $algorithm;

                break;
            }
        }

        if (null === $unavailableAlgorithm) {
            self::markTestSkipped('Tous les compresseurs sont disponibles sur ce système');
        }

        // Compressing with an unavailable algorithm should do nothing
        $compressor->compress($testFile, $unavailableAlgorithm);
        $compressor->waitForCompressionToFinish();

        // No compressed file should be created
        self::assertFileDoesNotExist($testFile.$unavailableAlgorithm->getExtension());
        self::assertFileExists($testFile);
    }

    public function testCompressCreatesCompressedFile(): void
    {
        $compressor = new Compressor();

        if ([] === $compressor->availableCompressors) {
            self::markTestSkipped('Aucun compresseur disponible sur ce système');
        }

        // Créer un fichier de test avec du contenu répétitif (compresse mieux)
        $testFile = $this->tempDir.'/test.html';
        $content = str_repeat('<p>Test content for compression</p>', 100);
        $this->filesystem->dumpFile($testFile, $content);

        // Tester chaque compresseur disponible
        foreach ($compressor->availableCompressors as $algorithm) {
            $compressor->compress($testFile, $algorithm);
            $compressor->waitForCompressionToFinish();

            $compressedFile = $testFile.$algorithm->getExtension();
            self::assertFileExists($compressedFile, \sprintf('Le fichier compressé avec %s devrait exister', $algorithm->value));
            self::assertGreaterThan(0, filesize($compressedFile), \sprintf('Le fichier compressé avec %s ne devrait pas être vide', $algorithm->value));

            // Nettoyer le fichier compressé pour le prochain test
            $this->filesystem->remove($compressedFile);
        }
    }

    public function testCompressedFileIsSmallerThanOriginal(): void
    {
        $compressor = new Compressor();

        if ([] === $compressor->availableCompressors) {
            self::markTestSkipped('Aucun compresseur disponible sur ce système');
        }

        // Créer un fichier HTML avec beaucoup de contenu répétitif
        $testFile = $this->tempDir.'/large-test.html';
        $content = '<!DOCTYPE html><html><head><title>Test</title></head><body>';
        $content .= str_repeat('<div class="content"><p>This is a test paragraph with repeated content.</p></div>', 500);
        $content .= '</body></html>';
        $this->filesystem->dumpFile($testFile, $content);

        $originalSize = filesize($testFile);

        foreach ($compressor->availableCompressors as $algorithm) {
            $compressor->compress($testFile, $algorithm);
            $compressor->waitForCompressionToFinish();

            $compressedFile = $testFile.$algorithm->getExtension();
            $compressedSize = filesize($compressedFile);

            self::assertLessThan(
                $originalSize,
                $compressedSize,
                \sprintf("Le fichier compressé avec %s devrait être plus petit que l'original", $algorithm->value)
            );

            // Nettoyer
            $this->filesystem->remove($compressedFile);
        }
    }

    public function testMultipleCompressionProcessesRunInParallel(): void
    {
        $compressor = new Compressor();

        if ([] === $compressor->availableCompressors) {
            self::markTestSkipped('Aucun compresseur disponible sur ce système');
        }

        // Créer plusieurs fichiers de test
        $files = [];
        for ($i = 1; $i <= 3; ++$i) {
            $testFile = $this->tempDir.\sprintf('/test%d.html', $i);
            $content = str_repeat(\sprintf('<p>Test content %d</p>', $i), 100);
            $this->filesystem->dumpFile($testFile, $content);
            $files[] = $testFile;
        }

        // Lancer plusieurs compressions sans attendre
        $algorithm = $compressor->availableCompressors[0];
        foreach ($files as $file) {
            $compressor->compress($file, $algorithm);
        }

        // Attendre que toutes les compressions se terminent
        $compressor->waitForCompressionToFinish();

        // Vérifier que tous les fichiers compressés existent
        foreach ($files as $file) {
            self::assertFileExists($file.$algorithm->getExtension());
        }
    }

    public function testDestructorWaitsForCompressionToFinish(): void
    {
        if ([] === (new Compressor())->availableCompressors) {
            self::markTestSkipped('Aucun compresseur disponible sur ce système');
        }

        // Créer un fichier de test
        $testFile = $this->tempDir.'/destructor-test.html';
        $content = str_repeat('<p>Test content</p>', 100);
        $this->filesystem->dumpFile($testFile, $content);

        $compressor = new Compressor();
        $algorithm = $compressor->availableCompressors[0];

        $compressor->compress($testFile, $algorithm);

        // Forcer la destruction explicite du compressor pour invoquer le destructeur
        unset($compressor);

        // Vérifier que le fichier compressé existe après la destruction
        self::assertFileExists($testFile.$algorithm->getExtension());
    }

    public function testWaitForCompressionCanBeCalledMultipleTimes(): void
    {
        $compressor = new Compressor();

        if ([] === $compressor->availableCompressors) {
            self::markTestSkipped('Aucun compresseur disponible sur ce système');
        }

        $testFile = $this->tempDir.'/test.html';
        $content = str_repeat('<p>Test</p>', 50);
        $this->filesystem->dumpFile($testFile, $content);

        $algorithm = $compressor->availableCompressors[0];
        $compressor->compress($testFile, $algorithm);

        // Appeler waitForCompressionToFinish plusieurs fois ne devrait pas poser de problème
        $compressor->waitForCompressionToFinish();
        $compressor->waitForCompressionToFinish();
        $compressor->waitForCompressionToFinish();

        self::assertFileExists($testFile.$algorithm->getExtension());
    }
}
