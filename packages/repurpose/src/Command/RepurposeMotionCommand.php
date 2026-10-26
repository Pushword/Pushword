<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Command;

use JsonException;
use Pushword\Core\Command\AgentOutputTrait;
use Pushword\Repurpose\Service\CarouselFactory;
use Pushword\Repurpose\Service\CreatorResolverInterface;
use Pushword\Repurpose\Service\MotionProjectExporter;
use RuntimeException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'pw:repurpose:motion', description: 'Export a carousel as an editable Remotion video project.')]
final class RepurposeMotionCommand
{
    use AgentOutputTrait;

    private bool $agentMode = false;

    public function __construct(
        private readonly CarouselFactory $factory,
        private readonly ValidatorInterface $validator,
        private readonly CreatorResolverInterface $creators,
        private readonly MotionProjectExporter $exporter,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Carousel JSON file, or - for stdin')]
        string $spec,
        #[Argument(description: 'New output directory (must not exist)')]
        string $outDir,
        #[Option(description: 'Site host used to resolve the creator byline')]
        string $host = '',
        #[Option(description: 'Output format: auto, agent or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->agentMode = $this->isAgentFormat($format);
        // Never overwrite a project's agent-authored scenes or timeline.
        if (file_exists($outDir) || is_link($outDir)) {
            return $this->fail($io, [['path' => 'outDir', 'message' => 'The output directory already exists. Choose a new directory.']]);
        }

        $content = '-' === $spec ? stream_get_contents(\STDIN) : (is_file($spec) ? file_get_contents($spec) : false);
        if (false === $content) {
            return $this->fail($io, [['path' => 'spec', 'message' => 'Cannot read the carousel spec.']]);
        }

        try {
            $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            return $this->fail($io, [['path' => 'spec', 'message' => 'Malformed JSON: '.$jsonException->getMessage()]]);
        }

        if (! \is_array($data) || array_is_list($data)) {
            return $this->fail($io, [['path' => 'spec', 'message' => 'The carousel spec must be a JSON object.']]);
        }

        /** @var array<string, mixed> $data */
        $carousel = $this->factory->fromArray($data);
        $issues = [];
        foreach ($this->validator->validate($carousel) as $violation) {
            $issues[] = ['path' => $violation->getPropertyPath(), 'message' => (string) $violation->getMessage()];
        }

        if ([] !== $issues) {
            return $this->fail($io, $issues);
        }

        try {
            $files = $this->exporter->files($carousel, $data, $this->creators->resolve($carousel, $host));
        } catch (RuntimeException $runtimeException) {
            return $this->fail($io, [['path' => 'audio', 'message' => $runtimeException->getMessage()]]);
        }

        $written = [];

        try {
            foreach ($files as $name => $bytes) {
                $path = rtrim($outDir, '/').'/'.$name;
                $this->filesystem->dumpFile($path, $bytes);
                $written[] = $path;
            }
        } catch (IOExceptionInterface $ioException) {
            return $this->fail($io, [['path' => $outDir, 'message' => $ioException->getMessage()]]);
        }

        if ($this->agentMode) {
            $this->writeAgentJson($io, [
                'tool' => 'pw:repurpose:motion', 'result' => 'done',
                'files' => $written,
                'next' => ['npm install', 'npm run check', 'npm run studio', 'npm run render'],
            ]);
        } else {
            $io->success('Motion project exported to '.$outDir.'. Run npm install, then npm run studio or npm run render inside it.');
        }

        return Command::SUCCESS;
    }

    /** @param list<array{path: string, message: string}> $issues */
    private function fail(SymfonyStyle $io, array $issues): int
    {
        if ($this->agentMode) {
            $this->writeAgentJson($io, ['tool' => 'pw:repurpose:motion', 'result' => 'failed', 'issues' => $issues]);
        } else {
            $io->table(['path', 'message'], array_map(static fn (array $issue): array => [$issue['path'], $issue['message']], $issues));
        }

        return Command::FAILURE;
    }
}
