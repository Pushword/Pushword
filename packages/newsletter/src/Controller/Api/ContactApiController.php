<?php

namespace Pushword\Newsletter\Controller\Api;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Repository\AudienceRepository;
use Pushword\Newsletter\Repository\ContactRepository;
use Pushword\Newsletter\Segment\SegmentException;
use Pushword\Newsletter\Segment\SegmentResolver;
use Pushword\Newsletter\Service\ContactManager;
use Pushword\Newsletter\Service\ContactMerger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Contacts over HTTP: what an external system (a CRM, a shop, a script) writes
 * into the list.
 *
 * `customProperties` is merge-patched rather than replaced, so a caller that
 * knows about `lastBoughtProduct` can update it without having to know — or
 * preserve — every other property already on the contact.
 */
#[IsGranted('ROLE_EDITOR')]
final class ContactApiController extends AbstractApiController
{
    /**
     * A phone number and an address the site knows separately are one person,
     * and this is the caller saying so. The row holding the address survives —
     * it is the one the unsubscribe links are keyed on.
     */
    private const array MERGE_PARAMETER = [
        'name' => 'merge',
        'in' => 'query',
        'description' => 'Join the contact this write names with the one already holding the identifier, instead of refusing',
        'schema' => ['type' => 'boolean'],
    ];

    public function __construct(
        private readonly ContactRepository $contactRepository,
        private readonly AudienceRepository $audienceRepository,
        private readonly ContactManager $contactManager,
        private readonly ContactMerger $contactMerger,
        private readonly SegmentResolver $segmentResolver,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/newsletter/contact', name: 'pushword_api_newsletter_contact_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $pagination = $this->paginationParams($request);

        $audience = null;
        if (null !== $request->query->get('audience')) {
            $audience = $this->audienceRepository->findOneBySlug($request->query->getString('audience'));
            if (! $audience instanceof Audience) {
                return $this->notFound('Audience not found');
            }
        }

        // A `segment` query runs the same criteria the campaigns use, so a caller
        // can count an audience before mailing it.
        if (null !== $request->query->get('segment')) {
            if (! $audience instanceof Audience) {
                return $this->badRequest('A segment query needs an audience');
            }

            return $this->listSegment($request, $audience, $pagination);
        }

        $queryBuilder = $this->contactRepository->createQueryBuilder('c');

        if ($audience instanceof Audience) {
            $queryBuilder->andWhere('c.audience = :audience')->setParameter('audience', $audience);
        }

        if (null !== $request->query->get('status')) {
            $queryBuilder->andWhere('c.status = :status')->setParameter('status', $request->query->getString('status'));
        }

        if (null !== $request->query->get('tag')) {
            $queryBuilder->andWhere('c.tags LIKE :tag')->setParameter('tag', '%"'.$request->query->getString('tag').'"%');
        }

        if (null !== $request->query->get('q')) {
            $queryBuilder->andWhere('c.email LIKE :q OR c.phone LIKE :q OR c.name LIKE :q')
                ->setParameter('q', '%'.$request->query->getString('q').'%');
        }

        $total = (int) (clone $queryBuilder)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        $queryBuilder->orderBy('c.id', 'ASC')
            ->setFirstResult($pagination['offset'])
            ->setMaxResults($pagination['perPage']);

        /** @var list<Contact> $contacts */
        $contacts = $queryBuilder->getQuery()->getResult();

        return $this->respond($this->paginated(array_map($this->toArray(...), $contacts), $total, $pagination['page'], $pagination['perPage']));
    }

    #[Route('/api/newsletter/contact', name: 'pushword_api_newsletter_contact_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);

        $audience = $this->audienceRepository->findOneBySlug(\is_string($data['audience'] ?? null) ? $data['audience'] : '');
        if (! $audience instanceof Audience) {
            return $this->notFound('Audience not found');
        }

        $email = \is_string($data['email'] ?? null) ? trim($data['email']) : '';
        $phone = Contact::normalizePhone(\is_string($data['phone'] ?? null) ? $data['phone'] : null);

        if ('' !== $email && false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $this->badRequest('Invalid email');
        }

        if ('' === $email && null === $phone) {
            return $this->badRequest('Missing email or phone');
        }

        // Keyed on the address when there is one, on the number otherwise:
        // an address is what makes two rows the same person.
        $existing = '' !== $email
            ? $this->contactRepository->findOneByEmail($audience, $email)
            : $this->contactRepository->findOneByPhone($audience, $phone);

        // An import of an already-consenting base skips the confirmation mail;
        // anything else follows the audience's own rule.
        $requireDoubleOptIn = 'subscribed' === ($data['status'] ?? null) ? false : null;

        try {
            if (null !== $phone && $request->query->getBoolean('merge')) {
                $existing = $this->joinOnUpsert($audience, $existing, $email, $phone);
            }

            $contact = $this->contactManager->subscribe(
                $audience,
                '' !== $email ? $email : null,
                \is_string($data['name'] ?? null) ? $data['name'] : null,
                \is_string($data['locale'] ?? null) ? $data['locale'] : null,
                [],
                \is_string($data['source'] ?? null) ? $data['source'] : 'api',
                null,
                null,
                $requireDoubleOptIn,
                $phone,
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            // The number is somebody else's. Joining two rows is a deliberate
            // operation, not something an upsert gets to do on the way past —
            // `?merge=true` is how a caller asks for it, and this is what
            // answers when even that cannot be honoured.
            return $this->respond(['error' => $invalidArgumentException->getMessage()], Response::HTTP_CONFLICT);
        }

        $this->apply($contact, $data);

        $rejected = $this->rejected($contact);
        if ($rejected instanceof JsonResponse) {
            return $rejected;
        }

        $this->entityManager->flush();

        return $this->respond(
            $this->toArray($contact),
            $existing instanceof Contact ? Response::HTTP_OK : Response::HTTP_CREATED,
        );
    }

    #[Route('/api/newsletter/contact/{id}', name: 'pushword_api_newsletter_contact_item', requirements: ['id' => '\d+'], methods: ['GET', 'PATCH', 'DELETE'])]
    public function item(int $id, Request $request): JsonResponse
    {
        $contact = $this->contactRepository->find($id);
        if (! $contact instanceof Contact) {
            return $this->notFound('Contact not found');
        }

        return match ($request->getMethod()) {
            'GET' => $this->respond($this->toArray($contact)),
            'PATCH' => $this->doUpdate($contact, $request),
            default => $this->doDelete($contact),
        };
    }

    #[Route('/api/newsletter/contact/{id}/unsubscribe', name: 'pushword_api_newsletter_contact_unsubscribe', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function unsubscribe(int $id): JsonResponse
    {
        $contact = $this->contactRepository->find($id);
        if (! $contact instanceof Contact) {
            return $this->notFound('Contact not found');
        }

        $this->contactManager->unsubscribe($contact);

        return $this->respond($this->toArray($contact));
    }

    #[Route('/api/newsletter/contact/{id}/bounce', name: 'pushword_api_newsletter_contact_bounce', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function bounce(int $id): JsonResponse
    {
        $contact = $this->contactRepository->find($id);
        if (! $contact instanceof Contact) {
            return $this->notFound('Contact not found');
        }

        $this->contactManager->markBounced($contact);

        return $this->respond($this->toArray($contact));
    }

    /** @param array{page: int, perPage: int, offset: int} $pagination */
    private function listSegment(Request $request, Audience $audience, array $pagination): JsonResponse
    {
        /** @var array<mixed> $segment */
        $segment = json_decode($request->query->getString('segment'), true) ?? [];

        try {
            $total = $this->segmentResolver->count($audience, $segment);
            $contacts = $this->segmentResolver->queryBuilder($audience, $segment)
                ->orderBy('c.id', 'ASC')
                ->setFirstResult($pagination['offset'])
                ->setMaxResults($pagination['perPage'])
                ->getQuery()
                ->getResult();
        } catch (SegmentException $segmentException) {
            return $this->badRequest($segmentException->getMessage());
        }

        /** @var list<Contact> $contacts */
        return $this->respond($this->paginated(
            array_map($this->toArray(...), $contacts),
            $total,
            $pagination['page'],
            $pagination['perPage'],
        ));
    }

    /**
     * The row this write is about, once the number it carries has been accounted
     * for. Only called when the caller asked for a merge.
     *
     * @throws InvalidArgumentException when the two rows cannot be joined
     */
    private function joinOnUpsert(Audience $audience, ?Contact $existing, string $email, string $phone): ?Contact
    {
        $holder = $this->contactRepository->findOneByPhone($audience, $phone);

        if (! $holder instanceof Contact || $holder === $existing) {
            return $existing;
        }

        if ($existing instanceof Contact) {
            return $this->contactMerger->merge($existing, $holder);
        }

        // There is nothing to join: no row holds this address yet, so the row
        // holding the number is the person and the address is what it lacked.
        if (null !== $holder->email) {
            throw new InvalidArgumentException(\sprintf('Contact #%s holds this number under another address.', $holder->id ?? '?'));
        }

        $holder->email = $email;
        $this->entityManager->flush();

        return $holder;
    }

    private function doUpdate(Contact $contact, Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);

        if ($request->query->getBoolean('merge')) {
            try {
                $contact = $this->joinOnPatch($contact, $data);
            } catch (InvalidArgumentException $invalidArgumentException) {
                return $this->respond(['error' => $invalidArgumentException->getMessage()], Response::HTTP_CONFLICT);
            }
        }

        $this->apply($contact, $data);

        $rejected = $this->rejected($contact);
        if ($rejected instanceof JsonResponse) {
            return $rejected;
        }

        $this->entityManager->flush();

        return $this->respond($this->toArray($contact));
    }

    /**
     * The row this write is about, once the identifiers it carries have been
     * accounted for. Only called when the caller asked for a merge.
     *
     * A write naming an identifier another row holds is otherwise refused (422),
     * and `?merge=true` is the caller saying the two rows are one person. What
     * comes back may not be the contact in the URL: the address decides, so a
     * number gaining an address answers with the addressed row.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException when the rows cannot be joined
     */
    private function joinOnPatch(Contact $contact, array $data): Contact
    {
        $holders = $this->holders($contact, $data);

        if (\count($holders) > 1) {
            throw new InvalidArgumentException('This write names two other contacts; join them one at a time.');
        }

        return [] === $holders ? $contact : $this->contactMerger->merge($contact, $holders[0]);
    }

    /**
     * The rows other than this one holding an identifier the write carries.
     *
     * @param array<string, mixed> $data
     *
     * @return list<Contact>
     */
    private function holders(Contact $contact, array $data): array
    {
        $holders = [];

        if (\is_string($data['email'] ?? null) && '' !== trim($data['email'])) {
            $holder = $this->contactRepository->findOneByEmail($contact->audience, trim($data['email']));

            if ($holder instanceof Contact && $holder !== $contact) {
                $holders[spl_object_id($holder)] = $holder;
            }
        }

        $phone = Contact::normalizePhone(\is_string($data['phone'] ?? null) ? $data['phone'] : null);

        if (null !== $phone) {
            $holder = $this->contactRepository->findOneByPhone($contact->audience, $phone);

            if ($holder instanceof Contact && $holder !== $contact) {
                $holders[spl_object_id($holder)] = $holder;
            }
        }

        return array_values($holders);
    }

    /**
     * The violations, if any — and the entity put back the way the database
     * holds it.
     *
     * {@see apply()} writes onto a managed object before anything has been
     * checked, so a refused write is still sitting in the unit of work when the
     * response leaves. The request ends without flushing and nothing reaches
     * the database, but the next flush of the same manager would write it: what
     * a 422 says is that nothing was written, and this is what makes it true.
     *
     * @return JsonResponse|null null when the contact is valid
     */
    private function rejected(Contact $contact): ?JsonResponse
    {
        $violations = $this->validator->validate($contact);

        if (0 === \count($violations)) {
            return null;
        }

        $this->entityManager->refresh($contact);

        return $this->validationErrors($violations);
    }

    private function doDelete(Contact $contact): JsonResponse
    {
        $this->entityManager->remove($contact);
        $this->entityManager->flush();

        return $this->noContent();
    }

    /** @param array<string, mixed> $data */
    private function apply(Contact $contact, array $data): void
    {
        if (\array_key_exists('name', $data) && \is_string($data['name'])) {
            $contact->name = $data['name'];
        }

        if (\array_key_exists('locale', $data) && \is_string($data['locale'])) {
            $contact->locale = $data['locale'];
        }

        if (\array_key_exists('phone', $data)) {
            $contact->phone = \is_string($data['phone']) ? $data['phone'] : null;
        }

        // Somebody known by phone alone gives their address later, and the
        // reverse. Which row the identifier may land on is the validator's
        // business, and it refuses one another contact already holds.
        if (\array_key_exists('email', $data)) {
            $contact->email = \is_string($data['email']) ? $data['email'] : null;
        }

        if (\array_key_exists('tags', $data) && \is_array($data['tags'])) {
            /** @var list<string> $tags */
            $tags = array_values(array_filter($data['tags'], is_string(...)));
            $contact->setTags($tags);
        }

        if (\array_key_exists('customProperties', $data) && \is_array($data['customProperties'])) {
            /** @var array<array-key, mixed> $properties */
            $properties = $data['customProperties'];
            foreach ($properties as $key => $value) {
                if (null === $value) {
                    $contact->removeCustomProperty((string) $key);

                    continue;
                }

                $contact->setCustomProperty((string) $key, $value);
            }
        }
    }

    /** @return array<string, mixed> */
    private function toArray(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'audience' => $contact->audience->slug,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'name' => $contact->name,
            'mailable' => $contact->isMailable(),
            'locale' => $contact->locale,
            'status' => $contact->getStatusLabel(),
            'tags' => $contact->getTagList(),
            'customProperties' => $contact->customProperties,
            'source' => $contact->source,
            'optinHost' => $contact->optinHost,
            'createdAt' => $contact->createdAt?->format(DateTimeInterface::ATOM),
            'confirmedAt' => $contact->confirmedAt?->format(DateTimeInterface::ATOM),
            'unsubscribedAt' => $contact->unsubscribedAt?->format(DateTimeInterface::ATOM),
            'bouncedAt' => $contact->bouncedAt?->format(DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public static function describe(): array
    {
        return [
            'paths' => [
                '/api/newsletter/contact' => [
                    'get' => [
                        'summary' => 'List contacts, optionally filtered by audience, status, tag or a segment expression',
                        'parameters' => [
                            ['name' => 'audience', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['pending', 'subscribed', 'unsubscribed', 'bounced']]],
                            ['name' => 'tag', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'segment', 'in' => 'query', 'description' => 'JSON criteria list; needs `audience`', 'schema' => ['type' => 'string']],
                            ['name' => 'q', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                    'post' => [
                        'summary' => 'Create or update a contact, keyed on (audience, email) or on (audience, phone) when no email is given',
                        'parameters' => [self::MERGE_PARAMETER],
                        'responses' => [
                            '200' => ['description' => 'Updated'],
                            '201' => ['description' => 'Created'],
                            '409' => ['description' => 'The number belongs to another contact, and merging it was not asked for or not possible'],
                        ],
                    ],
                ],
                '/api/newsletter/contact/{id}' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'get' => ['summary' => 'Get a contact', 'responses' => ['200' => ['description' => 'OK']]],
                    'patch' => [
                        'summary' => 'Update a contact; customProperties are merged, a null value removes a key',
                        'parameters' => [self::MERGE_PARAMETER],
                        'responses' => [
                            '200' => ['description' => 'OK — with `merge=true`, the body may carry another id than the one in the path'],
                            '409' => ['description' => 'The two rows cannot be joined'],
                            '422' => ['description' => 'An identifier another contact holds'],
                        ],
                    ],
                    'delete' => ['summary' => 'Delete a contact', 'responses' => ['204' => ['description' => 'Deleted']]],
                ],
                '/api/newsletter/contact/{id}/unsubscribe' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'post' => ['summary' => 'Record an opt-out', 'responses' => ['200' => ['description' => 'OK']]],
                ],
                '/api/newsletter/contact/{id}/bounce' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'post' => ['summary' => 'Record a permanent delivery failure', 'responses' => ['200' => ['description' => 'OK']]],
                ],
            ],
            'components' => [
                'schemas' => [
                    'NewsletterContact' => [
                        'type' => 'object',
                        'properties' => [
                            'audience' => ['type' => 'string'],
                            'email' => ['type' => 'string', 'nullable' => true],
                            'phone' => ['type' => 'string', 'nullable' => true, 'description' => 'Digits and a leading `+`; a contact may be keyed on it alone and is then never mailed'],
                            'name' => ['type' => 'string'],
                            'mailable' => ['type' => 'boolean', 'description' => 'Subscribed *and* holding an address'],
                            'locale' => ['type' => 'string'],
                            'status' => ['type' => 'string'],
                            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'customProperties' => ['type' => 'object'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
