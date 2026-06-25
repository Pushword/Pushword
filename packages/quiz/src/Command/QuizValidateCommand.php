<?php

namespace Pushword\Quiz\Command;

use Pushword\Conversation\Form\ConversationFormInterface;
use Pushword\Core\Command\AgentOutputTrait;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Quiz\Model\Quiz;
use Pushword\Quiz\Service\QuizBlockExtractor;
use Pushword\Quiz\Service\QuizFactory;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Lints the quiz blocks of a flat file (or stdin) against the very rules the
 * renderer and the API endpoint enforce. Prints precise `{path, message}`
 * violations and exits non-zero, so an agent can drop it into an edit→check loop
 * without a running server.
 */
#[AsCommand(
    name: 'pw:quiz:validate',
    description: 'Validate the quiz blocks of a flat file (or stdin) against the Quiz model.',
)]
final class QuizValidateCommand
{
    use AgentOutputTrait;

    private bool $agentMode = false;

    public function __construct(
        private readonly QuizFactory $factory,
        private readonly ValidatorInterface $validator,
        private readonly QuizBlockExtractor $extractor,
        private readonly TranslatorInterface $translator,
        private readonly SiteRegistry $apps,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Path to a file with quiz blocks, or - for stdin')]
        string $path = '-',
        #[Option(description: 'Output format: auto (compact JSON when an AI agent is detected), agent (force JSON), or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->agentMode = $this->isAgentFormat($format);

        $content = $this->read($path);
        if (null === $content) {
            if ($this->agentMode) {
                $this->writeAgentJson($io, [
                    'tool' => 'pw:quiz:validate',
                    'result' => 'failed',
                    'blocks_checked' => 0,
                    'errors' => 1,
                    'warnings' => 0,
                    'issues' => [['path' => $path, 'message' => \sprintf('Cannot read "%s".', $path)]],
                ]);

                return Command::FAILURE;
            }

            $io->error(\sprintf('Cannot read "%s".', $path));

            return Command::FAILURE;
        }

        $blocks = $this->extractor->extract($content);
        if ([] === $blocks) {
            if ($this->agentMode) {
                $this->writeAgentJson($io, [
                    'tool' => 'pw:quiz:validate',
                    'result' => 'passed',
                    'blocks_checked' => 0,
                    'errors' => 0,
                    'warnings' => 0,
                    'issues' => [],
                ]);

                return Command::SUCCESS;
            }

            $io->warning("No quiz block found (looked for {% quiz %}…{% endquiz %} and {{ quiz('…') }}).");

            return Command::SUCCESS;
        }

        $invalid = 0;
        $issues = [];
        $warningCount = 0;
        foreach ($blocks as $index => $block) {
            $label = \sprintf('Quiz #%d (line %d, %s)', $index + 1, $block['line'], $block['form']);
            if (! $this->validateBlock($io, $label, $block['json'], $issues, $warningCount)) {
                ++$invalid;
            }
        }

        if ($this->agentMode) {
            $this->writeAgentJson($io, [
                'tool' => 'pw:quiz:validate',
                'result' => 0 === $invalid ? 'passed' : 'failed',
                'blocks_checked' => \count($blocks),
                'errors' => \count($issues),
                'warnings' => $warningCount,
                'issues' => $issues,
            ]);

            return $invalid > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        if ($invalid > 0) {
            $io->error(\sprintf('%d of %d quiz block(s) invalid.', $invalid, \count($blocks)));

            return Command::FAILURE;
        }

        $io->success(\sprintf('All %d quiz block(s) valid.', \count($blocks)));

        return Command::SUCCESS;
    }

    /**
     * @param list<array{path: string, message: string}> $issues
     */
    private function validateBlock(SymfonyStyle $io, string $label, string $json, array &$issues, int &$warningCount): bool
    {
        try {
            $data = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            $issues[] = ['path' => $label, 'message' => 'Malformed JSON: '.$throwable->getMessage()];
            if (! $this->agentMode) {
                $io->section($label);
                $io->error('Malformed JSON: '.$throwable->getMessage());
            }

            return false;
        }

        if (! \is_array($data)) {
            $issues[] = ['path' => $label, 'message' => 'The quiz payload must be a JSON object.'];
            if (! $this->agentMode) {
                $io->section($label);
                $io->error('The quiz payload must be a JSON object.');
            }

            return false;
        }

        /** @var array<string, mixed> $data */
        $quiz = $this->factory->fromArray($data);
        $violations = $this->validator->validate($quiz);
        $warnings = $this->ctaWarnings($quiz);
        $warningCount += \count($warnings);

        if (\count($violations) > 0) {
            $rows = [];
            foreach ($violations as $violation) {
                $message = $this->translator->trans((string) $violation->getMessage(), [], 'validators');
                $issues[] = ['path' => $violation->getPropertyPath(), 'message' => $message];
                $rows[] = [$violation->getPropertyPath(), $message];
            }

            if (! $this->agentMode) {
                $io->section($label);
                $io->table(['path', 'message'], $rows);
                $this->printWarnings($io, $warnings);
            }

            return false;
        }

        if (! $this->agentMode) {
            $io->writeln(' <info>✓</info> '.$label.' — valid');
            $this->printWarnings($io, $warnings);
        }

        return true;
    }

    /**
     * @param string[] $warnings
     */
    private function printWarnings(SymfonyStyle $io, array $warnings): void
    {
        foreach ($warnings as $warning) {
            $io->writeln('   <comment>! '.$warning.'</comment>');
        }
    }

    /**
     * Soft check: a `cta` must name a registered conversation form type. Never
     * fails the lint — the form map is per-site, so we only flag what we can
     * confidently resolve as unknown.
     *
     * @return string[]
     */
    private function ctaWarnings(Quiz $quiz): array
    {
        $ctas = [];
        if (null !== $quiz->cta) {
            $ctas[] = $quiz->cta;
        }

        foreach ($quiz->levels as $level) {
            if (null !== $level->cta) {
                $ctas[] = $level->cta;
            }
        }

        $ctas = array_values(array_unique($ctas));
        if ([] === $ctas) {
            return [];
        }

        if (! interface_exists(ConversationFormInterface::class)) {
            return ['cta set ('.implode(', ', $ctas).') but pushword/conversation is not installed — the call to action will be ignored.'];
        }

        $known = $this->knownFormTypes();
        if ([] === $known) {
            return [];
        }

        $warnings = [];
        foreach ($ctas as $cta) {
            if (\in_array($cta, $known, true)) {
                continue;
            }

            if (\in_array(str_replace('-', '_', $cta), $known, true)) {
                continue;
            }

            if (class_exists('App\\Form\\'.ucfirst($cta))) {
                continue;
            }

            $warnings[] = \sprintf('unknown cta "%s" — not a registered form type (known: %s).', $cta, implode(', ', $known));
        }

        return $warnings;
    }

    /**
     * @return string[]
     */
    private function knownFormTypes(): array
    {
        try {
            return array_keys($this->apps->get()->getArray('conversation_form'));
        } catch (Throwable) {
            return [];
        }
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
