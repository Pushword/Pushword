<?php

namespace Pushword\Repurpose\Command;

use Pushword\Repurpose\Service\CarouselSchemaProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints the published JSON Schema of a carousel spec to stdout, so an agent can
 * pipe it to a file or feed it to a JSON-Schema-aware generator.
 */
#[AsCommand(
    name: 'pw:repurpose:schema',
    description: 'Print the JSON Schema describing a carousel spec.',
)]
final readonly class RepurposeSchemaCommand
{
    public function __construct(
        private CarouselSchemaProvider $schema,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $output->writeln($this->schema->json());

        return Command::SUCCESS;
    }
}
