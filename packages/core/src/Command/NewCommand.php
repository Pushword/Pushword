<?php

namespace Pushword\Core\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;

class NewCommand extends Command
{
    private string $projectDir;

    public function __construct(
        string $projectDir
    ) {
        $this->projectDir = $projectDir;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('pushword:new')
            ->setDescription('Add a new website into your config file (config/packages/pushword.yaml).');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configFile = $this->projectDir.'/config/packages/pushword.yaml';

        $helper = $this->getHelper('question');
        $question = new Question('Main domain (default: localhost.dev):', 'localhost.dev');
        $mainDomain = $helper->ask($input, $output, $question);

        $question = new Question('Locale (default: en|fr):', 'en|fr');
        $locales = $helper->ask($input, $output, $question);
        $locale = explode('|', $locales)[0];

        $config = file_exists($configFile) ? Yaml::parse(file_get_contents($configFile)) : ['pushword' => []];
        if (! isset($config['pushword']['apps'])) {
            $config['pushword']['apps'] = [];
        }
        $config['pushword']['apps'][] = ['hosts' => [$mainDomain], 'locale' => $locale, 'locales' => $locales];

        file_put_contents($configFile, Yaml::dump($config, 4));

        $output->writeln('<info>Config updated with success. Want more set more configuration options ? Open `config/packages/pushword.yaml`</info>');

        return Command::SUCCESS;
    }
}
