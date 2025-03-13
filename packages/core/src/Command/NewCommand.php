<?php

namespace Pushword\Core\Command;

use function Safe\file_put_contents;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'pushword:new')]
final class NewCommand extends Command
{
    public function __construct(
        private readonly string $projectDir
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Add a new website into your config file (config/packages/pushword.yaml).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configFile = $this->projectDir.'/config/packages/pushword.yaml';

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new Question('Main domain (default: localhost.dev):', 'localhost.dev');
        $mainDomain = $helper->ask($input, $output, $question);

        $question = new Question('Locale (default: en|fr):', 'en|fr');
        /** @var bool|float|int|string|null */
        $locales = $helper->ask($input, $output, $question);
        $locale = explode('|', \strval($locales))[0];

        $config = $this->initConfig($configFile, ['hosts' => [$mainDomain], 'locale' => $locale, 'locales' => $locales]);

        file_put_contents($configFile, Yaml::dump($config, 4));

        $output->writeln('<info>Config updated with success. Want more set more configuration options ? Open `config/packages/pushword.yaml`</info>');

        return Command::SUCCESS;
    }

    /**
     * @param array<mixed> $newHost
     *
     * @return array<mixed>
     */
    private function initConfig(string $configFile, array $newHost): array
    {
        $config = ($contentConfigFile = file_get_contents($configFile)) !== false
            ? Yaml::parse($contentConfigFile) : [];

        if (! \is_array($config)) {
            $config = [];
        }

        if (! isset($config['pushword']) || ! \is_array($config['pushword'])) {
            $config['pushword'] = [];
        }

        if (! isset($config['pushword']['apps']) || ! \is_array($config['pushword']['apps'])) {
            $config['pushword']['apps'] = [];
        }

        $config['pushword']['apps'][] = $newHost;

        return $config;
    }
}
