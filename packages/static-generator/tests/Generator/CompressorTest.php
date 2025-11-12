<?php

namespace Pushword\StaticGenerator\Tests\Generator;

use Exception;
use PHPUnit\Framework\TestCase;
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
        foreach ($compressor->availableCompressors as $compressorName) {
            self::assertContains($compressorName, Compressor::COMPRESSORS);
        }
    }

    public function testCompressWithUnavailableCompressorDoesNothing(): void
    {
        $compressor = new Compressor();

        // Créer un fichier de test
        $testFile = $this->tempDir.'/test.txt';
        $this->filesystem->dumpFile($testFile, 'Test content');

        // Si aucun compresseur n'est disponible, on skip le test
        if ([] === $compressor->availableCompressors) {
            self::markTestSkipped('Aucun compresseur disponible sur ce système');
        }

        // Tenter de compresser avec un compresseur non disponible (fictif)
        $unavailableCompressor = 'fake-compressor';
        if (! \in_array($unavailableCompressor, $compressor->availableCompressors, true)) {
            $compressor->compress($testFile, $unavailableCompressor);
            // Ne devrait pas générer d'exception ni créer de fichier
            self::assertFileDoesNotExist($testFile.'.fake');
        }

        self::assertFileExists($testFile);
    }

    public function testCompressThrowsExceptionForNonExistentFile(): void
    {
        $compressor = new Compressor();

        if ([] === $compressor->availableCompressors) {
            self::markTestSkipped('Aucun compresseur disponible sur ce système');
        }

        $nonExistentFile = $this->tempDir.'/non-existent.txt';
        $availableCompressor = $compressor->availableCompressors[0];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('does not exist or is not readable');

        $compressor->compress($nonExistentFile, $availableCompressor);
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
        foreach ($compressor->availableCompressors as $compressorName) {
            $extension = match ($compressorName) {
                Compressor::ZSTD => '.zst',
                Compressor::BROTLI => '.br',
                Compressor::GZIP => '.gz',
                default => throw new Exception('Unknown compressor'),
            };

            $compressor->compress($testFile, $compressorName);
            $compressor->waitForCompressionToFinish();

            $compressedFile = $testFile.$extension;
            self::assertFileExists($compressedFile, "Le fichier compressé avec {$compressorName} devrait exister");
            self::assertGreaterThan(0, filesize($compressedFile), "Le fichier compressé avec {$compressorName} ne devrait pas être vide");

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

        foreach ($compressor->availableCompressors as $compressorName) {
            $extension = match ($compressorName) {
                Compressor::ZSTD => '.zst',
                Compressor::BROTLI => '.br',
                Compressor::GZIP => '.gz',
                default => throw new Exception('Unknown compressor'),
            };

            $compressor->compress($testFile, $compressorName);
            $compressor->waitForCompressionToFinish();

            $compressedFile = $testFile.$extension;
            $compressedSize = filesize($compressedFile);

            self::assertLessThan(
                $originalSize,
                $compressedSize,
                "Le fichier compressé avec {$compressorName} devrait être plus petit que l'original"
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
            $testFile = $this->tempDir."/test{$i}.html";
            $content = str_repeat("<p>Test content {$i}</p>", 100);
            $this->filesystem->dumpFile($testFile, $content);
            $files[] = $testFile;
        }

        // Lancer plusieurs compressions sans attendre
        $availableCompressor = $compressor->availableCompressors[0];
        foreach ($files as $file) {
            $compressor->compress($file, $availableCompressor);
        }

        // Attendre que toutes les compressions se terminent
        $compressor->waitForCompressionToFinish();

        // Vérifier que tous les fichiers compressés existent
        $extension = match ($availableCompressor) {
            Compressor::ZSTD => '.zst',
            Compressor::BROTLI => '.br',
            Compressor::GZIP => '.gz',
            default => throw new Exception('Unknown compressor'),
        };

        foreach ($files as $file) {
            self::assertFileExists($file.$extension);
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
        $availableCompressor = $compressor->availableCompressors[0];

        $extension = match ($availableCompressor) {
            Compressor::ZSTD => '.zst',
            Compressor::BROTLI => '.br',
            Compressor::GZIP => '.gz',
            default => throw new Exception('Unknown compressor'),
        };

        $compressor->compress($testFile, $availableCompressor);

        // Forcer la destruction explicite du compressor pour invoquer le destructeur
        unset($compressor);

        // Vérifier que le fichier compressé existe après la destruction
        self::assertFileExists($testFile.$extension);
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

        $availableCompressor = $compressor->availableCompressors[0];
        $compressor->compress($testFile, $availableCompressor);

        // Appeler waitForCompressionToFinish plusieurs fois ne devrait pas poser de problème
        $compressor->waitForCompressionToFinish();
        $compressor->waitForCompressionToFinish();
        $compressor->waitForCompressionToFinish();

        $extension = match ($availableCompressor) {
            Compressor::ZSTD => '.zst',
            Compressor::BROTLI => '.br',
            Compressor::GZIP => '.gz',
            default => throw new Exception('Unknown compressor'),
        };

        self::assertFileExists($testFile.$extension);
    }
}
