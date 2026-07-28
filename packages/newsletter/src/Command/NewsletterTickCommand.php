<?php

namespace Pushword\Newsletter\Command;

use DateTimeImmutable;
use Pushword\Core\Command\AgentOutputTrait;
use Pushword\Newsletter\Repository\AutomationRepository;
use Pushword\Newsletter\Repository\CampaignRepository;
use Pushword\Newsletter\Service\AutomationRunner;
use Pushword\Newsletter\Service\CampaignSender;
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
    description: 'Send what is due: scheduled campaigns, paced broadcasts and automation steps',
)]
final class NewsletterTickCommand
{
    use AgentOutputTrait;

    private bool $agentMode = false;

    public function __construct(
        private readonly CampaignRepository $campaignRepository,
        private readonly CampaignSender $campaignSender,
        private readonly AutomationRepository $automationRepository,
        private readonly AutomationRunner $automationRunner,
        private readonly LockFactory $lockFactory,
        #[Autowire(param: 'pw.newsletter.send_batch')]
        private readonly int $defaultBatch,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Maximum number of mails this run may send', name: 'batch')]
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
            '%d campaign(s) armed, %d broadcast mail(s), %d enrollment(s), %d automation mail(s).',
            $report['armed'],
            $report['campaignMails'],
            $report['enrolled'],
            $report['automationMails'],
        ));

        return Command::SUCCESS;
    }

    /** @return array{armed: int, campaignMails: int, enrolled: int, automationMails: int, budgetLeft: int} */
    private function run(int $budget): array
    {
        $armed = 0;
        foreach ($this->campaignRepository->findDue(new DateTimeImmutable()) as $campaign) {
            $this->campaignSender->arm($campaign);
            ++$armed;
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

        $enrolled = 0;
        foreach ($this->automationRepository->findEnabled() as $automation) {
            $enrolled += $this->automationRunner->enroll($automation);
        }

        $automationMails = $this->automationRunner->advance($budget);

        return [
            'armed' => $armed,
            'campaignMails' => $campaignMails,
            'enrolled' => $enrolled,
            'automationMails' => $automationMails,
            'budgetLeft' => $budget - $automationMails,
        ];
    }
}
