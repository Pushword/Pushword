<?php

namespace Pushword\Quiz\Command;

use Pushword\Conversation\Form\ConversationFormInterface;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Quiz\Model\Quiz;
use Pushword\Quiz\Service\QuizBlockExtractor;
use Pushword\Quiz\Service\QuizFactory;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
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
final readonly class QuizValidateCommand
{
    public function __construct(
        private QuizFactory $factory,
        private ValidatorInterface $validator,
        private QuizBlockExtractor $extractor,
        private TranslatorInterface $translator,
        private SiteRegistry $apps,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Path to a file with quiz blocks, or - for stdin')]
        string $path = '-',
    ): int {
        $content = $this->read($path);
        if (null === $content) {
            $io->error(\sprintf('Cannot read "%s".', $path));

            return Command::FAILURE;
        }

        $blocks = $this->extractor->extract($content);
        if ([] === $blocks) {
            $io->warning("No quiz block found (looked for {% quiz %}…{% endquiz %} and {{ quiz('…') }}).");

            return Command::SUCCESS;
        }

        $invalid = 0;
        foreach ($blocks as $index => $block) {
            $label = \sprintf('Quiz #%d (line %d, %s)', $index + 1, $block['line'], $block['form']);
            if (! $this->validateBlock($io, $label, $block['json'])) {
                ++$invalid;
            }
        }

        if ($invalid > 0) {
            $io->error(\sprintf('%d of %d quiz block(s) invalid.', $invalid, \count($blocks)));

            return Command::FAILURE;
        }

        $io->success(\sprintf('All %d quiz block(s) valid.', \count($blocks)));

        return Command::SUCCESS;
    }

    private function validateBlock(SymfonyStyle $io, string $label, string $json): bool
    {
        try {
            $data = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            $io->section($label);
            $io->error('Malformed JSON: '.$throwable->getMessage());

            return false;
        }

        if (! \is_array($data)) {
            $io->section($label);
            $io->error('The quiz payload must be a JSON object.');

            return false;
        }

        /** @var array<string, mixed> $data */
        $quiz = $this->factory->fromArray($data);
        $violations = $this->validator->validate($quiz);
        $warnings = $this->ctaWarnings($quiz);

        if (\count($violations) > 0) {
            $io->section($label);
            $rows = [];
            foreach ($violations as $violation) {
                $rows[] = [
                    $violation->getPropertyPath(),
                    $this->translator->trans((string) $violation->getMessage(), [], 'validators'),
                ];
            }

            $io->table(['path', 'message'], $rows);
            $this->printWarnings($io, $warnings);

            return false;
        }

        $io->writeln(' <info>✓</info> '.$label.' — valid');
        $this->printWarnings($io, $warnings);

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
