<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Command;

use DateTimeImmutable;
use Pushword\Core\Command\AgentOutputTrait;
use Pushword\Newsletter\Repository\CampaignRepository;
use Pushword\Newsletter\Service\AutomationRunner;
use Pushword\Newsletter\Service\CampaignSender;
use Pushword\Newsletter\Sms\SmsCampaignSender;
use Pushword\Newsletter\Validation\CampaignValidationFailed;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;

/**
 * The clock. Run it every minute from system cron:
 *
 *     * * * * * cd /path/to/app && bin/console pw:newsletter:tick
 *
 * It is stateless and idempotent: each pass reads what is due and writes what it
 * did, so a missed run only delays work, and two overlapping runs are prevented
 * by a lock rather than by luck.
 */
#[AsCommand(
    name: 'pw:newsletter:tick',
    description: 'Send what is due: triggered sequences, scheduled campaigns, paced broadcasts and drip steps',
)]
final class NewsletterTickCommand
{
    use AgentOutputTrait;

    private bool $agentMode = false;

    public function __construct(
        private readonly CampaignRepository $campaignRepository,
        private readonly CampaignSender $campaignSender,
        private readonly AutomationRunner $automationRunner,
        private readonly LockFactory $lockFactory,
        private readonly SmsCampaignSender $sms,
        #[Autowire(param: 'pw.newsletter.send_batch')]
        private readonly int $defaultBatch,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Maximum number of emails and SMS this run may send', name: 'batch')]
        ?int $batch = null,
        #[Option(description: 'Output format: auto (compact JSON when an AI agent is detected), agent (force JSON), or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->agentMode = $this->isAgentFormat($format);
        $io = new SymfonyStyle($input, $output);

        $lock = $this->lockFactory->createLock('pushword_newsletter_tick');
        if (! $lock->acquire()) {
            if ($this->agentMode) {
                $this->writeAgentJson($output, ['tool' => 'pw:newsletter:tick', 'result' => 'blocked', 'locked' => true]);
            } else {
                $io->note('Another tick is running.');
            }

            return Command::SUCCESS;
        }

        try {
            $report = $this->run($batch ?? $this->defaultBatch);
        } finally {
            $lock->release();
        }

        if ($this->agentMode) {
            $this->writeAgentJson($output, ['tool' => 'pw:newsletter:tick', 'result' => 'done'] + $report);

            return Command::SUCCESS;
        }

        $io->success(\sprintf(
            '%d enrollment(s), %d campaign(s) scheduled by a trigger, %d cancelled, %d armed, %d blocked, %d broadcast mail(s), %d drip mail(s), %d SMS accepted, %d failed, %d excluded, %d awaiting retry.',
            $report['enrolled'],
            $report['scheduled'],
            $report['cancelled'],
            $report['armed'],
            $report['blocked'],
            $report['campaignMails'],
            $report['dripMails'],
            $report['smsAccepted'],
            $report['smsFailed'],
            $report['smsSkipped'],
            $report['smsErrors'],
        ));

        return Command::SUCCESS;
    }

    /** @return array{enrolled: int, scheduled: int, cancelled: int, armed: int, blocked: int, campaignMails: int, dripMails: int, budgetLeft: int, smsAccepted: int, smsFailed: int, smsSkipped: int, smsAttempts: int, smsErrors: int} */
    private function run(int $budget): array
    {
        $now = new DateTimeImmutable();

        // Before arming: something that happened long enough ago is meant to go
        // out in the same pass that noticed it, not a minute later.
        $triggered = $this->automationRunner->trigger($now);
        $cancelled = $this->automationRunner->cancelStale();

        $armed = 0;
        $blocked = 0;
        foreach ($this->campaignRepository->findDue($now) as $campaign) {
            try {
                $this->campaignSender->arm($campaign);
                ++$armed;
            } catch (CampaignValidationFailed) {
                ++$blocked;
            }
        }

        $campaignMails = 0;
        foreach ($this->campaignRepository->findSending() as $campaign) {
            if ($budget < 1) {
                break;
            }

            $sent = $this->campaignSender->drain($campaign, $budget);
            $budget -= $sent;
            $campaignMails += $sent;
        }

        $dripMails = $this->automationRunner->advance($budget);

        $sms = $this->sms->drain(max(0, $budget - $dripMails));

        return $triggered + $sms + [
            'cancelled' => $cancelled,
            'armed' => $armed,
            'blocked' => $blocked,
            'campaignMails' => $campaignMails,
            'dripMails' => $dripMails,
            'budgetLeft' => max(0, $budget - $dripMails - $sms['smsAttempts']),
        ];
    }
}
