<?php

namespace Pushword\Newsletter\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Core\Repository\TagsRepositoryTrait;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Enum\ContactStatus;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactRepository extends ServiceEntityRepository
{
    use TagsRepositoryTrait;

    /**
     * What the three methods below read at most. They answer "what has been
     * written here already" for the segment editor, so a base too large to scan
     * gives a shorter list of suggestions rather than a slow page.
     */
    private const int SUGGESTION_SCAN = 5000;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    /**
     * The tags the base carries, for the segment editor to offer.
     *
     * @return string[]
     */
    public function getAllTags(): array
    {
        /** @var array{tags: string[]}[] $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('c.tags')
            ->setMaxResults(self::SUGGESTION_SCAN)
            ->getQuery()
            ->getResult();

        return $this->flattenTags($rows);
    }

    /** @return string[] */
    public function getAllLocales(): array
    {
        /** @var array{locale: string}[] $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('DISTINCT c.locale')
            ->andWhere("c.locale != ''")
            ->getQuery()
            ->getResult();

        return array_column($rows, 'locale');
    }

    /**
     * The property keys the site has stored on someone. A contact's properties
     * are whatever an import or the API wrote — there is no declaration to read
     * them from, unlike a page's `page_properties`.
     *
     * @return string[]
     */
    public function getAllPropertyKeys(): array
    {
        /** @var array{customProperties: array<string, mixed>}[] $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('c.customProperties')
            ->setMaxResults(self::SUGGESTION_SCAN)
            ->getQuery()
            ->getResult();

        $keys = [];
        foreach ($rows as $row) {
            $keys = [...$keys, ...array_keys($row['customProperties'])];
        }

        return array_values(array_unique($keys));
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
     * Every subscription held by one address, on every audience and whatever its
     * status. The admin edits one of them at a time and has to see the others:
     * a row moved to another audience instead of added is a subscription lost.
     *
     * Unlike {@see findSubscribedSiblings()}, nothing is hidden here — the admin
     * is not a public page, and an unsubscribe is exactly what one needs to see.
     *
     * @return list<Contact>
     */
    public function findAllByEmail(string $email): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.audience', 'a')
            ->andWhere('c.email = :email')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->orderBy('a.slug', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * How the audience splits between statuses. Every status is present, so a
     * reader never has to tell "none yet" from "key missing".
     *
     * @return array<string, int>
     */
    public function countByStatus(Audience $audience): array
    {
        $counts = array_fill_keys(array_column(ContactStatus::cases(), 'value'), 0);

        $rows = $this->createQueryBuilder('c')
            ->select('c.status AS status, COUNT(c.id) AS total')
            ->andWhere('c.audience = :audience')
            ->setParameter('audience', $audience)
            ->groupBy('c.status')
            ->getQuery()
            ->getResult();

        foreach ($rows as $row) {
            $counts[$row['status']->value] = $row['total'];
        }

        return $counts;
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
            ->setParameter('email', $contact->email)
            ->setParameter('id', $contact->id)
            ->setParameter('subscribed', ContactStatus::Subscribed->value)
            ->setParameter('host', $contact->audience->mainHost)
            ->orderBy('a.slug', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
