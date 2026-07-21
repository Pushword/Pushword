<?php

namespace Pushword\Repurpose\Command;

use Pushword\Core\Command\AgentOutputTrait;
use Pushword\Repurpose\Service\CarouselFactory;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Validates a carousel spec file (or stdin) against the very rules the renderer
 * and the API endpoint enforce. Prints precise `{path, message}` violations and
 * exits non-zero, so an agent can drop it into an edit→check loop without a
 * running server.
 */
#[AsCommand(
    name: 'pw:repurpose:validate',
    description: 'Validate a carousel spec (JSON file or stdin) against the Carousel model.',
)]
final class RepurposeValidateCommand
{
    use AgentOutputTrait;

    private bool $agentMode = false;

    public function __construct(
        private readonly CarouselFactory $factory,
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Path to a carousel JSON spec, or - for stdin')]
        string $path = '-',
        #[Option(description: 'Output format: auto (compact JSON when an AI agent is detected), agent (force JSON), or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->agentMode = $this->isAgentFormat($format);

        $content = $this->read($path);
        if (null === $content) {
            return $this->fail($io, [['path' => $path, 'message' => \sprintf('Cannot read "%s".', $path)]]);
        }

        try {
            $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            return $this->fail($io, [['path' => $path, 'message' => 'Malformed JSON: '.$throwable->getMessage()]]);
        }

        if (! \is_array($data)) {
            return $this->fail($io, [['path' => $path, 'message' => 'The carousel spec must be a JSON object.']]);
        }

        /** @var array<string, mixed> $data */
        $carousel = $this->factory->fromArray($data);
        $violations = $this->validator->validate($carousel);

        $issues = [];
        foreach ($violations as $violation) {
            $issues[] = [
                'path' => $violation->getPropertyPath(),
                'message' => $this->translator->trans((string) $violation->getMessage(), $this->params($violation), 'validators'),
            ];
        }

        if ([] !== $issues) {
            return $this->fail($io, $issues);
        }

        return $this->pass($io);
    }

    /**
     * @return array<string, string>
     */
    private function params(ConstraintViolationInterface $violation): array
    {
        $params = [];
        foreach ($violation->getParameters() as $key => $value) {
            $params[(string) $key] = \is_scalar($value) ? (string) $value : '';
        }

        return $params;
    }

    /**
     * @param list<array{path: string, message: string}> $issues
     */
    private function fail(SymfonyStyle $io, array $issues): int
    {
        if ($this->agentMode) {
            $this->writeAgentJson($io, [
                'tool' => 'pw:repurpose:validate',
                'result' => 'failed',
                'errors' => \count($issues),
                'issues' => $issues,
            ]);

            return Command::FAILURE;
        }

        $rows = array_map(static fn (array $i): array => [$i['path'], $i['message']], $issues);
        $io->table(['path', 'message'], $rows);
        $io->error(\sprintf('%d violation(s).', \count($issues)));

        return Command::FAILURE;
    }

    private function pass(SymfonyStyle $io): int
    {
        if ($this->agentMode) {
            $this->writeAgentJson($io, [
                'tool' => 'pw:repurpose:validate',
                'result' => 'passed',
                'errors' => 0,
                'issues' => [],
            ]);

            return Command::SUCCESS;
        }

        $io->success('Carousel spec is valid.');

        return Command::SUCCESS;
    }

    private function read(string $path): ?string
    {
        if ('-' === $path) {
            $content = stream_get_contents(\STDIN);

            return false === $content ? null : $content;
        }

        if (! is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return false === $content ? null : $content;
    }
}
