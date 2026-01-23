<?php

namespace Pushword\Core\Command;

use Exception;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

trait AskIfNotSettedTrait
{
    private function getOrAskIfNotSetted(
        InputInterface $input,
        OutputInterface $output,
        string $argument,
        ?string $default = '',
        bool $allowEmpty = false,
        ?string $currentValue = null,
    ): string {
        if (null !== $currentValue && '' !== $currentValue) {
            return $currentValue;
        }

        $helper = new QuestionHelper();
        /** @var bool|float|int|string|null */
        $argumentValue = $input->getArgument($argument);

        if (null !== $argumentValue && '' !== $argumentValue) {
            return (string) $argumentValue;
        }

        // If empty is allowed and no value provided, return the default
        if ($allowEmpty) {
            return $default ?? '';
        }

        $defaultDisplay = null !== $default && '' !== $default ? ' (default: '.$default.')' : '';
        $question = new Question($argument.$defaultDisplay.':', $default);
        if ('password' === $argument) {
            $question->setHidden(true);
        }

        /** @var bool|float|int|resource|string|null */
        $argumentValue = $helper->ask($input, $output, $question);

        if (null === $argumentValue || '' === $argumentValue) {
            $output->writeln('<error>'.$argument.' is required.</error>');

            return $this->getOrAskIfNotSetted($input, $output, $argument, $default, $allowEmpty, $currentValue);
        }

        if (! \is_scalar($argumentValue)) {
            throw new Exception();
        }

        return (string) $argumentValue;
    }
}
