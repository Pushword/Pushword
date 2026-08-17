<?php

namespace Pushword\Installer;

/**
 * Whether this machine can run Pushword as it is, and whether Docker is an option —
 * the two facts `install.php` needs to recommend one over the other.
 *
 * Composer's own platform check does not answer the first: pushword/core only declares
 * ext-gd, so an install resolves and installs happily on a PHP that
 * later fails to boot for want of intl.
 */
final readonly class SystemCheck
{
    /**
     * What https://pushword.piedweb.com/installation asks for, minus the extensions no
     * PHP build omits. gd and imagick are handled apart: either one is enough.
     *
     * @var list<string>
     */
    private const array REQUIRED_EXTENSIONS = [
        'bcmath',
        'curl',
        'dom',
        'exif',
        'fileinfo',
        'iconv',
        'intl',
        'libxml',
        'mbstring',
        'pdo',
        'zip',
    ];

    /**
     * @param list<string> $missing extension names this PHP does not have
     */
    public function __construct(
        public array $missing,
        public bool $dockerAvailable,
    ) {
    }

    public static function probe(?string $databaseUrl = null): self
    {
        return new self(self::missingExtensions($databaseUrl), self::hasWorkingDocker());
    }

    /**
     * Asking only makes sense when both answers are actually available.
     */
    public function shouldAsk(): bool
    {
        return $this->dockerAvailable;
    }

    public function recommendsDocker(): bool
    {
        return [] !== $this->missing;
    }

    /**
     * The "why" shown next to the recommendation.
     */
    public function reason(): string
    {
        if ([] !== $this->missing) {
            return 'this PHP is missing '.implode(', ', array_map(static fn (string $ext): string => 'ext-'.$ext, $this->missing))
                .', which the image ships ready to run';
        }

        return 'this PHP already has everything Pushword needs, and running it directly'
            .' keeps bin/console, the profiler and Xdebug one command away';
    }

    /**
     * @return list<string>
     */
    private static function missingExtensions(?string $databaseUrl): array
    {
        $databaseUrl ??= 'sqlite:';
        $databaseExtensions = match (true) {
            str_starts_with($databaseUrl, 'postgresql:'), str_starts_with($databaseUrl, 'postgres:') => ['pdo_pgsql'],
            str_starts_with($databaseUrl, 'mysql:'), str_starts_with($databaseUrl, 'mariadb:') => ['pdo_mysql'],
            default => ['pdo_sqlite', 'sqlite3'],
        };

        $missing = array_values(array_filter(
            [...self::REQUIRED_EXTENSIONS, ...$databaseExtensions],
            static fn (string $extension): bool => ! \extension_loaded($extension)
        ));

        // Image processing needs one of the two, not both.
        if (! \extension_loaded('gd') && ! \extension_loaded('imagick')) {
            $missing[] = 'gd';
        }

        sort($missing);

        return $missing;
    }

    /**
     * `docker info` is what separates "the client is on PATH" from "the daemon
     * answers"; without it a laptop with Docker Desktop stopped looks ready.
     */
    private static function hasWorkingDocker(): bool
    {
        return self::succeeds('docker info') && self::succeeds('docker compose version');
    }

    private static function succeeds(string $command): bool
    {
        $output = [];
        $status = 1;
        exec($command.' 2>&1', $output, $status);

        return 0 === $status;
    }
}
