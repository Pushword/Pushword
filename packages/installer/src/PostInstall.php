<?php

namespace Pushword\Installer;

use Exception;
use LogicException;
use Symfony\Component\Filesystem\Filesystem;

if (! class_exists(Filesystem::class)) {
    require_once __DIR__.'/vendor/symfony/filesystem/Filesystem.php';
}

class PostInstall
{
    public static function runPostUpdate(): void // Event $event
    {
        $packages = self::scanDir('vendor/pushword');

        // core runs last: it rewrites the DSN, updates the schema, loads the fixtures
        // and installs the bundle assets — each of which must see every other bundle
        // already registered in config/bundles.php by that bundle's own install.php.
        usort($packages, static fn (string $a, string $b): int => ('core' === $a ? 1 : 0) <=> ('core' === $b ? 1 : 0));

        foreach ($packages as $package) {
            if (! file_exists('var/installer/'.md5($package)) && file_exists($installer = 'vendor/pushword/'.$package.'/install.php')) {
                self::dumpFile('var/installer/'.md5($package), 'done');
                echo '~ Executing '.$package.' install action.'.\chr(10);
                include $installer;
            }
        }
    }

    /**
     * @return array<int, string>
     */
    public static function scanDir(string $dirPath): array
    {
        if (($dir = scandir($dirPath)) === false) {
            throw new LogicException();
        }

        return array_filter($dir, static fn (string $path): bool => ! \in_array($path, ['.', '..'], true));
    }

    public static function copy(string $source, string $dest): void
    {
        new Filesystem()->copy($source, $dest, true);
    }

    public static function mirror(string $source, string $dest): void
    {
        new Filesystem()->mirror($source, $dest);
    }

    /**
     * @param string|string[] $path
     */
    public static function remove(array|string $path): void
    {
        new Filesystem()->remove($path);
    }

    public static function dumpFile(string $path, string $content): void
    {
        new Filesystem()->dumpFile($path, $content);
    }

    public static function replace(string $file, string $search, string $replace): void
    {
        $content = file_get_contents($file);
        if (false === $content) {
            throw new Exception('`'.$file.'` not found');
        }

        $count = 0;
        $content = str_replace($search, $replace, $content, $count);
        if (1 !== $count) {
            echo '⚠ Warning: Could not replace `'.$search.'` by `'.$replace.'` in '.$file.\chr(10);

            return;
        }

        file_put_contents($file, $content);
    }

    public const INSERT_AT_BEGINNING = 'atBeggining';

    public const INSERT_AT_END = 'atEnd';

    public static function insertIn(string $file, string $toAdd, string $where = self::INSERT_AT_BEGINNING): void
    {
        $content = (string) @file_get_contents($file);
        if (str_contains($content, $toAdd)) {
            return;
        }

        if (self::INSERT_AT_BEGINNING === $where) {
            $content = $toAdd.$content;
        } elseif (self::INSERT_AT_END === $where) {
            $content .= $toAdd;
        } else {
            throw new Exception();
        }

        self::dumpFile($file, $content);
    }

    /**
     * Register a bundle in config/bundles.php.
     *
     * Pushword ships no Flex recipe, so nothing but this used to add a bundle to the
     * kernel: `composer require pushword/<bundle>` installed code the app never loaded,
     * while the routes the same install step wrote pointed at it. Every bundle now
     * registers itself.
     *
     * The FQCN is matched anywhere in the file, which covers both the inline form Flex
     * writes and the imported short form a hand-edited bundles.php uses — the latter
     * still carries the FQCN in its `use` statement.
     */
    public static function registerBundle(string $bundle): void
    {
        $file = 'config/bundles.php';
        $content = (string) @file_get_contents($file);
        if (str_contains($content, $bundle)) {
            return;
        }

        $registered = preg_replace('/\n\];\s*$/', "\n    ".$bundle."::class => ['all' => true],\n];\n", $content, 1);
        if (null === $registered || $registered === $content) {
            echo '⚠ Warning: Could not register `'.$bundle.'` in '.$file.'. Add it by hand.'.\chr(10);

            return;
        }

        echo '~~ Registering '.$bundle.\chr(10);
        self::dumpFile($file, $registered);
    }

    /**
     * Import a bundle's routes in config/routes.yaml.
     *
     * insertIn() only recognises the exact block it would write, which a routes.yaml
     * edited by hand never reproduces — double quotes instead of single, another key,
     * an attribute import of the controller directory. Matching the bundle reference
     * instead keeps an upgrade from importing the same resource a second time under a
     * duplicate YAML key.
     */
    public static function importRoutes(string $key, string $resource): void
    {
        $file = 'config/routes.yaml';
        $bundle = strstr($resource, '/', true);
        if (false !== $bundle && str_contains((string) @file_get_contents($file), $bundle)) {
            return;
        }

        echo '~~ Adding '.$key.' routes'.\chr(10);
        self::insertIn($file, $key.":\n    resource: '".$resource."'\n");
    }

    public static function isRoot(): bool
    {
        return file_exists('vendor');
    }

    /**
     * Whether an install step may stop and ask the person running it something.
     *
     * Composer exports COMPOSER_NO_INTERACTION when it was itself run with
     * `--no-interaction`; beyond that, a question is only answerable if stdin is a
     * terminal — never in CI, a Dockerfile or a provisioning script.
     */
    public static function isInteractive(): bool
    {
        $noInteraction = (string) getenv('COMPOSER_NO_INTERACTION');
        if ('' !== $noInteraction && '0' !== $noInteraction) {
            return false;
        }

        return \defined('STDIN') && stream_isatty(\STDIN);
    }

    /**
     * Read a yes/no answer. Only ever called behind isInteractive().
     *
     * Anything unrecognised — including an empty line — keeps the default, so a typo
     * lands on the recommendation rather than silently against it.
     */
    public static function confirm(string $question, bool $default): bool
    {
        echo $question;

        $answer = fgets(\STDIN);

        return match (false === $answer ? '' : strtolower(trim($answer))) {
            'y', 'yes' => true,
            'n', 'no' => false,
            default => $default,
        };
    }
}
