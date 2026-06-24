<?php

namespace Pushword\Quiz\Command;

use Pushword\Quiz\Service\QuizSchemaProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints the published JSON Schema of a quiz payload to stdout, so an agent can
 * pipe it to a file or feed it to a JSON-Schema-aware generator.
 */
#[AsCommand(
    name: 'pw:quiz:schema',
    description: 'Print the JSON Schema describing a quiz payload.',
)]
final readonly class QuizSchemaCommand
{
    public function __construct(
        private QuizSchemaProvider $schema,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $output->writeln($this->schema->json());

        return Command::SUCCESS;
    }
}
