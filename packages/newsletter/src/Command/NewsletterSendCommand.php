<?php

namespace Pushword\Newsletter\Command;

use Pushword\Core\Command\AgentOutputTrait;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Repository\CampaignRepository;
use Pushword\Newsletter\Service\CampaignSender;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Arm a draft campaign. The mails themselves go out from `pw:newsletter:tick`,
 * at the cadence — so this returns immediately, whatever the audience size.
 */
#[AsCommand(
    name: 'pw:newsletter:send',
    description: "Freeze a campaign's recipients and open its send (the tick delivers them)",
)]
final class NewsletterSendCommand
{
    use AgentOutputTrait;

    private bool $agentMode = false;

    public function __construct(
        private readonly CampaignRepository $campaignRepository,
        private readonly CampaignSender $campaignSender,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument(description: 'Campaign id')]
        int $campaign = 0,
        #[Option(description: 'Output format: auto (compact JSON when an AI agent is detected), agent (force JSON), or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->agentMode = $this->isAgentFormat($format);
        $io = new SymfonyStyle($input, $output);

        $entity = $this->campaignRepository->find($campaign);

        if (! $entity instanceof Campaign) {
            if ($this->agentMode) {
                $this->writeAgentJson($output, ['tool' => 'pw:newsletter:send', 'result' => 'failed', 'error' => 'campaign not found', 'campaign' => $campaign]);
            } else {
                $io->error(\sprintf('Campaign #%d not found.', $campaign));
            }

            return Command::FAILURE;
        }

        if (! $entity->isDraft() && ! $entity->isScheduled()) {
            if ($this->agentMode) {
                $this->writeAgentJson($output, ['tool' => 'pw:newsletter:send', 'result' => 'failed', 'error' => 'already sent', 'status' => $entity->getStatusLabel()]);
            } else {
                $io->error(\sprintf('Campaign #%d is %s.', $campaign, $entity->getStatusLabel()));
            }

            return Command::FAILURE;
        }

        $recipients = $this->campaignSender->arm($entity);

        if ($this->agentMode) {
            $this->writeAgentJson($output, [
                'tool' => 'pw:newsletter:send',
                'result' => 'done',
                'campaign' => $entity->id,
                'recipients' => $recipients,
                'rateSeconds' => $entity->getEffectiveRateSeconds(),
            ]);

            return Command::SUCCESS;
        }

        $io->success(\sprintf(
            '%d recipient(s) queued at 1 mail / %d s.',
            $recipients,
            $entity->getEffectiveRateSeconds(),
        ));

        return Command::SUCCESS;
    }
}
