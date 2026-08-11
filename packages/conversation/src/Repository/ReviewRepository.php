<?php

namespace Pushword\Conversation\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Conversation\Entity\Review;

/**
 * Bound to Review::class so every query it builds — createQueryBuilder(),
 * find(), findBy() — carries the single-table discriminator
 * (`message_type = 1`). MessageRepository queries the whole message table.
 *
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Review::class);
    }
}
