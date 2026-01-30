<?php

namespace Pushword\Flat\Importer;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Core\Site\SiteRegistry;

/**
 * Permit to find error in image or link.
 *
 * @template T of object
 */
abstract class AbstractImporter
{
    public function __construct(protected EntityManagerInterface $em, protected SiteRegistry $apps)
    {
    }

    /**
     * @return bool true if the file was actually imported, false if skipped
     */
    abstract public function import(string $filePath, DateTimeInterface $lastEditDateTime): bool;

    public function finishImport(): void
    {
        $this->em->flush();
    }

    protected static function underscoreToCamelCase(string $string): string
    {
        $str = str_replace('_', '', ucwords($string, '_'));

        return lcfirst($str);
    }

    protected function getMimeTypeFromFile(string $filePath): string
    {
        $finfo = finfo_open(\FILEINFO_MIME_TYPE);
        if (false === $finfo) {
            throw new Exception('finfo is not working');
        }

        return (string) finfo_file($finfo, $filePath);
    }

    /**
     * Nettoie récursivement les chaînes de caractères pour s'assurer qu'elles sont en UTF-8 valide.
     * Cette fonction est utilisée pour éviter les erreurs de sérialisation JSON avec des caractères malformés.
     * Ne nettoie que si nécessaire (si json_encode échoue).
     */
    protected function sanitizeUtf8(mixed $data): mixed
    {
        // Teste d'abord si les données peuvent être encodées en JSON
        if (false !== json_encode($data, \JSON_UNESCAPED_UNICODE)) {
            return $data;
        }

        // Si l'encodage JSON échoue, nettoie les données
        if (\is_string($data)) {
            // Supprime les caractères UTF-8 invalides en utilisant iconv avec //IGNORE
            $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $data);
            if (false === $cleaned) {
                // Si iconv échoue, supprime manuellement les octets invalides UTF-8
                $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $data);
                $cleaned = mb_convert_encoding($cleaned ?? '', 'UTF-8', 'UTF-8');
            }

            // Vérifie à nouveau que la chaîne peut être encodée en JSON
            if (false === json_encode($cleaned, \JSON_UNESCAPED_UNICODE)) {
                // Si l'encodage JSON échoue encore, supprime les caractères problématiques
                $cleaned = mb_convert_encoding($cleaned, 'UTF-8', 'UTF-8');
                $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE//TRANSLIT', $cleaned) ?: $cleaned;
            }

            return $cleaned;
        }

        if (\is_array($data)) {
            return array_map($this->sanitizeUtf8(...), $data);
        }

        return $data;
    }
}
