<?php

namespace Pushword\Newsletter\Command;

use InvalidArgumentException;
use Pushword\Core\Command\AgentOutputTrait;
use Pushword\Core\Service\Email\NotificationEmailSender;
use Pushword\Newsletter\Service\BounceCollector;
use RuntimeException;
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
        private readonly NotificationEmailSender $emailSender,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Maildir to read, instead of the configured mailbox', name: 'maildir')]
        ?string $maildir = null,
        #[Option(description: 'Report what would happen, marking nobody and filing nothing', name: 'dry-run')]
        bool $dryRun = false,
        #[Option(description: 'Mail the summary to this address, and only when something moved', name: 'notify')]
        ?string $notify = null,
        #[Option(description: 'Output format: auto (compact JSON when an AI agent is detected), agent (force JSON), or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->agentMode = $this->isAgentFormat($format);
        $io = new SymfonyStyle($input, $output);

        try {
            $source = $this->bounceCollector->source($maildir, $dryRun);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return $this->fail($output, $io, 'no readable bounce mailbox', $invalidArgumentException->getMessage());
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
            $report = $this->bounceCollector->collect($source, $dryRun);
        } catch (InvalidArgumentException|RuntimeException $throwable) {
            // A mailbox that cannot be reached — credentials, DNS, a folder that
            // is not there — is a failed run and not a crash: the next quarter
            // of an hour tries again, and nothing has been read or marked.
            return $this->fail($output, $io, 'the mailbox could not be read', $throwable->getMessage());
        } finally {
            $lock->release();
        }

        $notified = $this->notify($notify, $report, $dryRun);

        if ($this->agentMode) {
            $this->writeAgentJson($output, ['tool' => 'pw:newsletter:bounces', 'result' => 'done', 'dryRun' => $dryRun, 'notified' => $notified] + $report);

            return Command::SUCCESS;
        }

        if (0 === $report['scanned']) {
            $io->success('Nothing to read.');

            return Command::SUCCESS;
        }

        $io->success($this->summary($report, $dryRun));

        $this->detail($io, $report);

        return Command::SUCCESS;
    }

    /**
     * The recap, sent **only when something actually moved**.
     *
     * The command runs four times an hour. A site that mails its operator every
     * run trains them to filter it, which costs the one message that mattered.
     * Zero movement, zero mail — and a dry run never mails at all, since it is
     * the run one performs precisely to decide whether to let it act.
     *
     * @param array{scanned: int, failures: int, marked: int, soft: int, foreign: int, unfiled: int, unknown: list<string>} $report
     *
     * @return bool whether a mail was handed to the transport
     */
    private function notify(?string $notify, array $report, bool $dryRun): bool
    {
        if (null === $notify || '' === trim($notify) || $dryRun) {
            return false;
        }

        if (0 === $report['marked'] && 0 === $report['failures']) {
            return false;
        }

        $envelope = $this->emailSender->resolveEnvelope(null, [trim($notify)]);
        $body = $this->summary($report, false)."\n".$this->plainDetail($report);

        return $this->emailSender->send(
            $envelope,
            \sprintf('%d address(es) dropped from the newsletter', $report['marked']),
            nl2br(htmlspecialchars($body)),
            $body,
        );
    }

    /** @param array{scanned: int, failures: int, marked: int, ...} $report */
    private function summary(array $report, bool $dryRun): string
    {
        return \sprintf(
            '%d message(s) read, %d permanent failure(s), %d address(es) %s.',
            $report['scanned'],
            $report['failures'],
            $report['marked'],
            $dryRun ? 'would be dropped' : 'dropped',
        );
    }

    /**
     * The console rendering: the same three sentences, each in the style that
     * says how much it matters.
     *
     * @param array{soft: int, foreign: int, unfiled: int, unknown: list<string>, ...} $report
     */
    private function detail(SymfonyStyle $io, array $report): void
    {
        $unknown = $this->unknownLine($report);
        if (null !== $unknown) {
            $io->note($unknown);
        }

        // Only worth a line on screen when it is not all zeroes; the mail keeps
        // it either way, being a record rather than a glance.
        if ($report['soft'] > 0 || $report['foreign'] > 0) {
            $io->writeln('<comment>'.$this->ignoredLine($report).'</comment>');
        }

        $unfiled = $this->unfiledLine($report);
        if (null !== $unfiled) {
            $io->warning($unfiled);
        }
    }

    /**
     * The same, for a mail that has no styles to say it with.
     *
     * @param array{soft: int, foreign: int, unfiled: int, unknown: list<string>, ...} $report
     */
    private function plainDetail(array $report): string
    {
        $lines = [
            $this->ignoredLine($report),
            $this->unknownLine($report),
            $this->unfiledLine($report),
        ];

        return implode("\n", array_filter($lines, static fn (?string $line): bool => null !== $line));
    }

    /**
     * The mailbox collects the failures of everything else the app sends, so
     * some of what it holds concerns nobody on the list.
     *
     * @param array{unknown: list<string>, ...} $report
     */
    private function unknownLine(array $report): ?string
    {
        if ([] === $report['unknown']) {
            return null;
        }

        return \sprintf(
            '%d bounced address(es) are on no list, and were left alone: %s',
            \count($report['unknown']),
            implode(', ', \array_slice($report['unknown'], 0, 10)),
        );
    }

    /** @param array{soft: int, foreign: int, ...} $report */
    private function ignoredLine(array $report): string
    {
        return \sprintf(
            '%d temporary failure(s) ignored, %d message(s) were not delivery reports.',
            $report['soft'],
            $report['foreign'],
        );
    }

    /** @param array{unfiled: int, ...} $report */
    private function unfiledLine(array $report): ?string
    {
        return $report['unfiled'] > 0
            ? \sprintf('%d message(s) could not be marked read and will be read again.', $report['unfiled'])
            : null;
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
