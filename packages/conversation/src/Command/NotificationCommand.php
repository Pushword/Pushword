<?php
/**
 * Command to send report (best use with cron).
 */

namespace Pushword\Conversation\Command;

use Pushword\Conversation\Service\NewMessageMailNotifier;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NotificationCommand extends Command
{
    /**
     * @var string|null
     */
    protected static $defaultName = 'pushword:conversation:notify';

    protected \Pushword\Conversation\Service\NewMessageMailNotifier $notifier;

    public function __construct(
        NewMessageMailNotifier $newMessageMailNotifier
    ) {
        $this->notifier = $newMessageMailNotifier;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Send a mail (notification) with the latests messages stored (this comand is useful to program a cron).')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (true === $this->notifier->send()) {
            $output->writeln('Notification sent with success.');

            return 0;
        }

        $output->writeln('No new message.');

        return 0;
    }
}
