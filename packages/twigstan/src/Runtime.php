<?php

declare(strict_types=1);

namespace Pushword\TwigStan;

use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

final class Runtime
{
    public static function projectDirectory(): string
    {
        return getenv('PUSHWORD_TWIGSTAN_PROJECT_DIR') ?: (getcwd() ?: throw new RuntimeException('Cannot find the project directory.'));
    }

    public static function toolDirectory(): string
    {
        return __DIR__.'/../tools';
    }

    public static function configurationFile(): string
    {
        return getenv('PUSHWORD_TWIGSTAN_CONFIGURATION') ?: self::projectDirectory().'/twigstan.php';
    }

    public static function cacheDirectory(): string
    {
        return self::projectDirectory().'/.twigstan';
    }

    /** @param list<string> $argv */
    public static function run(array $argv): int
    {
        $project = self::projectDirectory();
        $coverage = \in_array('--check-coverage', $argv, true);
        $arguments = array_values(array_filter(\array_slice($argv, 1), static fn (string $argument): bool => '--check-coverage' !== $argument));
        $input = new ArgvInput([$argv[0], ...$arguments]);
        $configuration = Path::makeAbsolute($input->getParameterOption(['--configuration', '-c'], 'twigstan.php'), $project);
        foreach ([
            'PUSHWORD_TWIGSTAN_PROJECT_DIR' => $project,
            'PUSHWORD_TWIGSTAN_CONFIGURATION' => $configuration,
            'PAO_DISABLE' => '1',
        ] as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value;
            putenv($name.'='.$value);
        }

        if (! is_file($configuration)) {
            throw new RuntimeException('TwigStan configuration not found: '.$configuration);
        }

        $install = new Process(['composer', 'install', '--no-interaction', '--no-progress'], self::toolDirectory(), timeout: 300);
        if (0 !== self::execute($install)) {
            return 1;
        }

        $analysis = new Process([\PHP_BINARY, self::toolDirectory().'/runner.php', 'analyze', '--configuration='.$configuration, ...$arguments], $project, timeout: null);
        $exit = self::execute($analysis);
        if (0 !== $exit || ! $coverage) {
            return $exit;
        }

        require self::toolDirectory().'/bootstrap.php';

        return Coverage::check(require $configuration);
    }

    private static function execute(Process $process): int
    {
        return $process->run(static function (string $type, string $buffer): void {
            fwrite(Process::ERR === $type ? \STDERR : \STDOUT, $buffer);
        });
    }
}
