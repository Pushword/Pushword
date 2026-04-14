<?php

namespace Pushword\Flat\Command;

use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\FlatFileContentDirFinder;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;

#[AsCommand(
    name: 'pw:flat:lint',
    description: 'Validate YAML front matter in flat .md files.'
)]
final readonly class FlatLintCommand
{
    public function __construct(
        private SiteRegistry $apps,
        private FlatFileContentDirFinder $contentDirFinder,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Host to lint (optional)', name: 'host')]
        ?string $host = null,
    ): int {
        $resolvedHost = $host ?? $this->apps->getMainHost();
        if (null === $resolvedHost) {
            $output->writeln('<error>No host configured.</error>');

            return Command::FAILURE;
        }

        $contentDir = $this->contentDirFinder->get($resolvedHost);

        $files = $this->collectMarkdownFiles($contentDir);
        $errorCount = 0;

        foreach ($files as $filePath) {
            $content = file_get_contents($filePath);
            if (false === $content) {
                continue;
            }

            // Strip UTF-8 BOM
            if (str_starts_with($content, "\xEF\xBB\xBF")) {
                $content = substr($content, 3);
            }

            try {
                YamlFrontMatter::parse($content);
            } catch (ParseException $e) {
                ++$errorCount;
                $relativePath = str_replace($contentDir.'/', '', $filePath);
                $output->writeln(\sprintf(
                    '<error>%s (line %d): %s</error>',
                    $relativePath,
                    $e->getParsedLine(),
                    $e->getMessage(),
                ));
            }
        }

        if (0 === $errorCount) {
            $output->writeln(\sprintf('<info>All %d file(s) in %s have valid YAML front matter.</info>', \count($files), $resolvedHost));

            return Command::SUCCESS;
        }

        $output->writeln(\sprintf('<comment>%d file(s) with YAML errors.</comment>', $errorCount));

        return Command::FAILURE;
    }

    /**
     * @return string[]
     */
    private function collectMarkdownFiles(string $dir): array
    {
        if (! file_exists($dir)) {
            return [];
        }

        $files = [];

        /** @var string[] $entries */
        $entries = scandir($dir);
        foreach ($entries as $entry) {
            if (\in_array($entry, ['.', '..'], true)) {
                continue;
            }

            if (str_ends_with($entry, '~')) {
                continue;
            }

            if (str_contains($entry, '~conflict-')) {
                continue;
            }

            $path = $dir.'/'.$entry;
            if (is_dir($path)) {
                $files = [...$files, ...$this->collectMarkdownFiles($path)];

                continue;
            }

            if (str_ends_with($path, '.md')) {
                $files[] = $path;
            }
        }

        return $files;
    }
}
