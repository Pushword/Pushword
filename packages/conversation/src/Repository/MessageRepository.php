<?php

namespace Pushword\Conversation\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Pushword\Conversation\Entity\MessageInterface;

/*
 * @extends ServiceEntityRepository<MessageInterface>
 *
 * @method MessageInterface|null find($id, $lockMode = null, $lockVersion = null)
 * @method MessageInterface|null findOneBy(array $criteria, array $orderBy = null)
 * @method MessageInterface[]    findAll()
 * @method MessageInterface[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 */
class MessageRepository extends ServiceEntityRepository // @phpstan-ignore-line
{
    /**
     * @return MessageInterface[]
     */
    public function getMessagesPublishedByReferring(string $referring, string $orderBy = 'createdAt DESC', int $limit = 0)
    {
        $orderBy = explode(' ', $orderBy);

        $queryBuilder = $this->createQueryBuilder('m')
            ->andWhere('m.publishedAt is NOT NULL')
            ->andWhere('m.referring =  :referring')
            ->setParameter('referring', $referring)
            ->orderBy('m.'.$orderBy[0], $orderBy[1]);
        if (0 !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getResult(); // @phpstan-ignore-line
    }
}
