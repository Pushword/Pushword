<?php

namespace Pushword\Newsletter\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Contact;

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
}
