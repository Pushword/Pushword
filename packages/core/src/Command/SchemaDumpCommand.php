<?php

namespace Pushword\Core\Command;

use Pushword\Api\Service\PageFrontmatterMapper;
use Pushword\Core\Entity\Page;
use Pushword\Core\PropertySchema\PagePropertySchemaRegistry;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Dumps, per host, everything a page author (human or agent) may write:
 * the declared custom properties, the keys managed by dedicated admin
 * fields, and the core columns the frontmatter accepts. Replaces the
 * hand-maintained property lists in per-site CLAUDE.md files.
 */
#[AsCommand(name: 'pw:schema:dump', description: 'Dump declared page properties and accepted frontmatter keys per host')]
final class SchemaDumpCommand
{
    use AgentOutputTrait;

    private bool $agentMode = false;

    /** @param class-string<Page> $pageClass */
    public function __construct(
        private readonly PagePropertySchemaRegistry $schemaRegistry,
        private readonly SiteRegistry $apps,
        private readonly string $pageClass,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Limit the dump to one host', name: 'host')]
        ?string $host = null,
        #[Option(description: 'Output format: auto (compact JSON when an AI agent is detected), agent (force JSON), or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->agentMode = $this->isAgentFormat($format);

        $hosts = null !== $host && '' !== $host
            ? [$this->apps->get($host)->getMainHost()]
            : $this->apps->getHosts();

        $managedKeys = new $this->pageClass()->getManagedPropertyKeys();
        $frontmatterColumns = class_exists(PageFrontmatterMapper::class) ? PageFrontmatterMapper::RESERVED_FRONTMATTER_KEYS : [];

        $data = [];
        foreach ($hosts as $mainHost) {
            $data[$mainHost] = [
                'page_properties' => $this->schemaRegistry->describe($mainHost),
                'managed_keys' => $managedKeys,
                'frontmatter_columns' => $frontmatterColumns,
            ];
        }

        if ($this->agentMode) {
            $this->writeAgentJson($output, ['tool' => 'pw:schema:dump', 'result' => 'done', 'hosts' => $data]);

            return Command::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);
        foreach ($data as $mainHost => $hostData) {
            $io->section($mainHost);

            $rows = [];
            foreach ($hostData['page_properties'] as $name => $descriptor) {
                $rows[] = [
                    $name,
                    $descriptor['type'] ?? 'string',
                    true === ($descriptor['required'] ?? false) ? 'yes' : '',
                    isset($descriptor['constraints']) ? json_encode($descriptor['constraints'], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) : '',
                ];
            }

            if ([] === $rows) {
                $io->text('No declared page properties.');
            } else {
                $io->table(['property', 'type', 'required', 'constraints'], $rows);
            }

            $io->text('Managed keys (dedicated fields): '.implode(', ', $managedKeys));
            if ([] !== $frontmatterColumns) {
                $io->text('Frontmatter columns: '.implode(', ', $frontmatterColumns));
            }
        }

        return Command::SUCCESS;
    }
}
