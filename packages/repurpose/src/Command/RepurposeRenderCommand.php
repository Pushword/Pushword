<?php

namespace Pushword\Repurpose\Command;

use Pushword\Core\Command\AgentOutputTrait;
use Pushword\Repurpose\Service\CarouselFactory;
use Pushword\Repurpose\Service\SlideRenderer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

/**
 * Renders a carousel spec (JSON file or stdin) to one self-contained SVG per slide
 * in the target directory. The SVGs are the canonical artifact — the same bytes
 * an agent reads, the admin previews and the exporter rasterises.
 */
#[AsCommand(
    name: 'pw:repurpose:render',
    description: 'Render a carousel spec to SVG slides in a directory.',
)]
final class RepurposeRenderCommand
{
    use AgentOutputTrait;

    private bool $agentMode = false;

    public function __construct(
        private readonly CarouselFactory $factory,
        private readonly SlideRenderer $renderer,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Path to a carousel JSON spec, or - for stdin')]
        string $spec = '-',
        #[Argument(description: 'Output directory for the SVG slides')]
        string $outDir = '.',
        #[Option(description: 'Output format: auto, agent (force JSON) or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->agentMode = $this->isAgentFormat($format);

        $content = '-' === $spec ? stream_get_contents(\STDIN) : (is_file($spec) ? file_get_contents($spec) : false);
        if (false === $content) {
            return $this->fail($io, \sprintf('Cannot read "%s".', $spec));
        }

        try {
            $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            return $this->fail($io, 'Malformed JSON: '.$throwable->getMessage());
        }

        if (! \is_array($data)) {
            return $this->fail($io, 'The carousel spec must be a JSON object.');
        }

        /** @var array<string, mixed> $data */
        $carousel = $this->factory->fromArray($data);
        $svgs = $this->renderer->renderDeck($carousel);

        $this->filesystem->mkdir($outDir);
        $written = [];
        foreach ($svgs as $index => $svg) {
            $file = rtrim($outDir, '/').'/slide-'.($index + 1).'.svg';
            $this->filesystem->dumpFile($file, $svg);
            $written[] = $file;
        }

        if ($this->agentMode) {
            $this->writeAgentJson($io, [
                'tool' => 'pw:repurpose:render',
                'result' => 'done',
                'slides' => \count($written),
                'files' => $written,
            ]);

            return Command::SUCCESS;
        }

        $io->success(\sprintf('Rendered %d slide(s) to %s.', \count($written), $outDir));

        return Command::SUCCESS;
    }

    private function fail(SymfonyStyle $io, string $message): int
    {
        if ($this->agentMode) {
            $this->writeAgentJson($io, ['tool' => 'pw:repurpose:render', 'result' => 'failed', 'error' => $message]);
        } else {
            $io->error($message);
        }

        return Command::FAILURE;
    }
}
