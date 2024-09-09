<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Service\ImageManager;
use Symfony\Component\Console\Input\InputInterface;

trait ImageCommandTrait
{
    private EntityManagerInterface $em;

    private ImageManager $imageManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        ImageManager $imageManager,
    ) {
        $this->em = $entityManager;
        $this->imageManager = $imageManager;

        parent::__construct();
    }

    /**
     * @return array<int, Media>
     */
    protected function getMedias(InputInterface $input): array
    {
        $repo = $this->em->getRepository(Media::class);

        if (null !== $input->getArgument('media')) {
            return $repo->findBy(['media' => $input->getArgument('media')]);
        }

        return $repo->findAll();
    }
}
