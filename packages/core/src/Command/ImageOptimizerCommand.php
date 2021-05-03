<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Service\ImageManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ImageOptimizerCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var string
     */
    private $mediaClass;

    /**
     * @var ImageManager
     */
    private $imageManager;

    public function __construct(
        EntityManagerInterface $em,
        ImageManager $imageManager,
        string $mediaClass
    ) {
        $this->em = $em;
        $this->mediaClass = $mediaClass;
        $this->imageManager = $imageManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('pushword:image:optimize')
            ->setDescription('Generate all images cache')
            ->addArgument('media', InputArgument::OPTIONAL, 'Image name (eg: filename.jpg).');
    }

    protected function getMedias(InputInterface $input)
    {
        $repo = $this->em->getRepository($this->mediaClass);

        if ($input->getArgument('media')) {
            return $repo->findBy(['media' => $input->getArgument('media')]);
        }

        return $repo->findAll();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $medias = $this->getMedias($input);

        $progressBar = new ProgressBar($output, \count($medias));
        $progressBar->setMessage('');
        $progressBar->setFormat("%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% \r\n %message%");
        $progressBar->start();
        foreach ($medias as $media) {
            if ($this->imageManager->isImage($media)) {
                $progressBar->setMessage($media->getPath());
                $this->imageManager->optimize($media);
            }
            $progressBar->advance();
        }
        $progressBar->finish();

        return 0;
    }
}
