<?php

namespace Pushword\Newsletter\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Enum\ContactStatus;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    public function findOneByEmail(Audience $audience, string $email): ?Contact
    {
        return $this->findOneBy(['audience' => $audience, 'email' => mb_strtolower(trim($email))]);
    }

    public function findOneByToken(string $token): ?Contact
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * The same person on the other lists of the same host: same address, an
     * audience sharing the `mainHost`, still subscribed.
     *
     * The host is the boundary on purpose — several brands can live on one
     * install, and a link belonging to one of them must never say what the
     * others know about the address.
     *
     * @return list<Contact>
     */
    public function findSubscribedSiblings(Contact $contact): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.audience', 'a')
            ->andWhere('c.email = :email')
            ->andWhere('c.id != :id')
            ->andWhere('c.status = :subscribed')
            ->andWhere('a.mainHost = :host')
            ->setParameter('email', $contact->getEmail())
            ->setParameter('id', $contact->id)
            ->setParameter('subscribed', ContactStatus::Subscribed->value)
            ->setParameter('host', $contact->getAudience()->getMainHost())
            ->orderBy('a.slug', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
