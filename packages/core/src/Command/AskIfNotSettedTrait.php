<?php

namespace Pushword\Core\Command;

use Exception;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

trait AskIfNotSettedTrait
{
    private function getOrAskIfNotSetted(InputInterface $input, OutputInterface $output, string $argument, string $default = ''): string
    {
        $helper = new QuestionHelper();
        /** @var bool|float|int|string|null */
        $argumentValue = $input->getArgument($argument);

        if (null !== $argumentValue) {
            return (string) $argumentValue;
        }

        $question = new Question($argument.('' !== $default ? ' (default: '.$default.')' : '').':', $default);
        if ('password' === $argument) {
            $question->setHidden(true);
        }

        /** @var bool|float|int|resource|string|null */
        $argumentValue = $helper->ask($input, $output, $question);

        if (null === $argumentValue) {
            $output->writeln('<error>'.$argument.' is required.</error>');

            return $this->getOrAskIfNotSetted($input, $output, $argument, $default);
        }

        if (! \is_scalar($argumentValue)) {
            throw new Exception();
        }

        return (string) $argumentValue;
    }
}
