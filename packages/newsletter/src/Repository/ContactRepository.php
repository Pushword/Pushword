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
     * The languages one audience can actually be mailed in, sorted.
     *
     * An audience spanning several locale hosts is still one list, so what it
     * covers is a question about its contacts and not about the site's
     * configuration: a base nobody has yet subscribed to in German does not need
     * a German anything.
     *
     * Scoped to who would receive a mail, since that is what both readers ask
     * it for — whether a broadcast needs splitting by language, and how many
     * translations a campaign still owes. A language only somebody unsubscribed
     * or somebody the site can only phone reads is not one of them.
     *
     * @return list<string>
     */
    public function localesIn(Audience $audience): array
    {
        /** @var array{locale: string}[] $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('DISTINCT c.locale')
            ->andWhere('c.audience = :audience')
            ->andWhere('c.status = :subscribed')
            ->andWhere('c.email IS NOT NULL')
            ->andWhere("c.locale != ''")
            ->setParameter('audience', $audience)
            ->setParameter('subscribed', ContactStatus::Subscribed->value)
            ->orderBy('c.locale', 'ASC')
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

    /** Normalised the way {@see Contact::$phone} stores it, or the lookup misses its own rows. */
    public function findOneByPhone(Audience $audience, string $phone): ?Contact
    {
        $normalized = Contact::normalizePhone($phone);

        return null === $normalized ? null : $this->findOneBy(['audience' => $audience, 'phone' => $normalized]);
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
     * A contact with no address has no sibling: the address is what makes two
     * rows the same person. Answering the question at all for a null one would
     * match every phone-only contact in the database and show a hundred
     * strangers as one person's subscriptions.
     *
     * @return list<Contact>
     */
    public function findAllByEmail(?string $email): array
    {
        if (null === $email || '' === trim($email)) {
            return [];
        }

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
     * How many of the subscribed can actually be mailed. Reported next to the
     * subscribed count rather than instead of it: an audience where the two
     * differ is one where somebody would otherwise believe a campaign reached
     * everybody who consented.
     */
    public function countMailable(Audience $audience): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.audience = :audience')
            ->andWhere('c.status = :subscribed')
            ->andWhere('c.email IS NOT NULL')
            ->setParameter('audience', $audience)
            ->setParameter('subscribed', ContactStatus::Subscribed->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * The same person on the other lists of the same host: same address, an
     * audience sharing the `mainHost`, still subscribed.
     *
     * The host is the boundary on purpose — several brands can live on one
     * install, and a link belonging to one of them must never say what the
     * others know about the address.
     *
     * Nothing for a contact with no address, for the reason
     * {@see findAllByEmail()} gives — and here it would be in public.
     *
     * @return list<Contact>
     */
    public function findSubscribedSiblings(Contact $contact): array
    {
        if (null === $contact->email) {
            return [];
        }

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
