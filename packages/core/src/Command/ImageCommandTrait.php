<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\ImageManager;
use Symfony\Component\Console\Input\InputInterface;

trait ImageCommandTrait
{
    /**
     * @var class-string
     */
    private string $mediaClass;

    private EntityManagerInterface $em;

    private ImageManager $imageManager;

    /**
     * @param class-string $mediaClass
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ImageManager $imageManager,
        string $mediaClass
    ) {
        $this->em = $entityManager;
        $this->mediaClass = $mediaClass;
        $this->imageManager = $imageManager;

        parent::__construct();
    }

    /**
     * @return array<int, MediaInterface>
     */
    protected function getMedias(InputInterface $input): array
    {
        /** @var MediaRepository */
        $repo = $this->em->getRepository($this->mediaClass);

        if (null !== $input->getArgument('media')) {
            return $repo->findBy(['media' => $input->getArgument('media')]);
        }

        return $repo->findAll();
    }
}
