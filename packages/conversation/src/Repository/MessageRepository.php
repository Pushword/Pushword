<?php

namespace Pushword\Conversation\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Pushword\Conversation\Entity\Message;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @extends ServiceEntityRepository<Message>
 *
 * @method Message|null find($id, $lockMode = null, $lockVersion = null)
 * @method Message|null findOneBy(array $criteria, array $orderBy = null)
 * @method Message[]    findAll()
 * @method Message[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
#[AutoconfigureTag('doctrine.repository_service')]
class MessageRepository extends ServiceEntityRepository
{
    /**
     * @return Message[]
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

        return $queryBuilder->getQuery()->getResult();
    }
}
