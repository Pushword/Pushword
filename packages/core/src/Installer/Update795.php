<?php

namespace Pushword\Core\Installer;

use Exception;
use Pushword\Installer\PostAutoloadDump;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Executed via Pushword\Installer\PostAutoloadDump::postAutoloadDump.
 */
class Update795
{
    public function run(): void
    {
        $this->updateDatabaseSchema();
    }

    private function updateDatabaseSchema(): void
    {
        $migrationsDir = PostAutoloadDump::getKernel()->getProjectDir().'/src/Migrations';
        if (! file_exists($migrationsDir)) {
            mkdir($migrationsDir);
        }

        $application = new Application(PostAutoloadDump::getKernel());
        $application->setCatchExceptions(false);
        $commands = ['make:migration', 'doctrine:migrations:migrate'];
        foreach ($commands as $command) {
            $return = exec('cd '.PostAutoloadDump::getKernel()->getProjectDir().' && APP_ENV=dev php bin/console '.$command.' -q');
            //$application->run(new ArgvInput(['command'=> $command, '-q']), new NullOutput());
            if ('' !== $return) {
                throw new Exception('Update database schema failed, please do it yoursef `php bin/console make:migration && php bin/console doctrine:migrations:migrate`');
            }
        }
    }
}
