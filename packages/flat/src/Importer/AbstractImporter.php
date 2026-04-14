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
     * Recursively sanitize strings to ensure valid UTF-8.
     * Prevents JSON serialization errors from malformed characters.
     * Only sanitizes when needed (when json_encode fails).
     */
    protected function sanitizeUtf8(mixed $data): mixed
    {
        if (false !== json_encode($data, \JSON_UNESCAPED_UNICODE)) {
            return $data;
        }

        if (\is_string($data)) {
            // Remove invalid UTF-8 characters using iconv with //IGNORE
            $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $data);
            if (false === $cleaned) {
                // If iconv fails, manually remove invalid UTF-8 bytes
                $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $data);
                $cleaned = mb_convert_encoding($cleaned ?? '', 'UTF-8', 'UTF-8');
            }

            if (false === json_encode($cleaned, \JSON_UNESCAPED_UNICODE)) {
                // If JSON encoding still fails, strip remaining problematic characters
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
