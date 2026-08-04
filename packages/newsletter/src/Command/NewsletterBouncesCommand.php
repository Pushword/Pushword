<?php

namespace Pushword\Newsletter\Command;

use Pushword\Core\Command\AgentOutputTrait;
use Pushword\Newsletter\Service\BounceCollector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

/**
 * Read the mailbox delivery failures come back to, and drop the addresses that
 * failed for good.
 *
 * Run it from cron next to the tick. It is not part of the tick on purpose:
 * sending is due every minute, while a mailbox is worth reading every quarter
 * of an hour, and a maildir the host has not created yet must not be able to
 * hold up a campaign.
 *
 *     0,15,30,45 * * * * cd /path/to/app && bin/console pw:newsletter:bounces -q
 *
 * Start with `--dry-run`: it says what it would take off the list without
 * touching the database or the mailbox.
 */
#[AsCommand(
    name: 'pw:newsletter:bounces',
    description: 'Read the bounce mailbox and drop the addresses that failed for good',
)]
final class NewsletterBouncesCommand
{
    use AgentOutputTrait;

    private bool $agentMode = false;

    public function __construct(
        private readonly BounceCollector $bounceCollector,
        private readonly LockFactory $lockFactory,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Maildir to read, instead of the configured one', name: 'maildir')]
        ?string $maildir = null,
        #[Option(description: 'Report what would happen, marking nobody and filing nothing', name: 'dry-run')]
        bool $dryRun = false,
        #[Option(description: 'Output format: auto (compact JSON when an AI agent is detected), agent (force JSON), or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->agentMode = $this->isAgentFormat($format);
        $io = new SymfonyStyle($input, $output);

        $mailbox = $this->bounceCollector->maildir($maildir);

        if (null === $mailbox) {
            return $this->fail($output, $io, 'no bounce mailbox configured', 'Set `newsletter.bounce_maildir` (the mailbox `framework.mailer.envelope.sender` points at) or pass --maildir.');
        }

        if (! is_dir($mailbox)) {
            return $this->fail($output, $io, 'maildir not found', \sprintf('No maildir at "%s".', $mailbox));
        }

        // Two runs reading the same mailbox would file each other's messages.
        $lock = $this->lockFactory->createLock('pushword_newsletter_bounces');

        if (! $lock->acquire()) {
            if ($this->agentMode) {
                $this->writeAgentJson($output, ['tool' => 'pw:newsletter:bounces', 'result' => 'blocked', 'locked' => true]);
            } else {
                $io->note('Another run is reading the mailbox.');
            }

            return Command::SUCCESS;
        }

        try {
            $report = $this->bounceCollector->collect($mailbox, $dryRun);
        } finally {
            $lock->release();
        }

        if ($this->agentMode) {
            $this->writeAgentJson($output, ['tool' => 'pw:newsletter:bounces', 'result' => 'done', 'dryRun' => $dryRun] + $report);

            return Command::SUCCESS;
        }

        if (0 === $report['scanned']) {
            $io->success('Nothing to read.');

            return Command::SUCCESS;
        }

        $io->success(\sprintf(
            '%d message(s) read, %d permanent failure(s), %d address(es) %s.',
            $report['scanned'],
            $report['failures'],
            $report['marked'],
            $dryRun ? 'would be dropped' : 'dropped',
        ));

        $this->detail($io, $report);

        return Command::SUCCESS;
    }

    /** @param array{soft: int, foreign: int, unfiled: int, unknown: list<string>, ...} $report */
    private function detail(SymfonyStyle $io, array $report): void
    {
        if ([] !== $report['unknown']) {
            $io->note(\sprintf(
                "%d bounced address(es) are on no list, and were left alone:\n%s",
                \count($report['unknown']),
                implode(', ', \array_slice($report['unknown'], 0, 10)),
            ));
        }

        if ($report['soft'] > 0 || $report['foreign'] > 0) {
            $io->writeln(\sprintf(
                '<comment>%d temporary failure(s) ignored, %d message(s) were not delivery reports.</comment>',
                $report['soft'],
                $report['foreign'],
            ));
        }

        if ($report['unfiled'] > 0) {
            $io->warning(\sprintf('%d message(s) could not be moved to cur/ and will be read again.', $report['unfiled']));
        }
    }

    private function fail(OutputInterface $output, SymfonyStyle $io, string $error, string $message): int
    {
        if ($this->agentMode) {
            $this->writeAgentJson($output, ['tool' => 'pw:newsletter:bounces', 'result' => 'failed', 'error' => $error]);
        } else {
            $io->error($message);
        }

        return Command::FAILURE;
    }
}
