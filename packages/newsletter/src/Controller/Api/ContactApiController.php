<?php

namespace Pushword\Newsletter\Controller\Api;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Repository\AudienceRepository;
use Pushword\Newsletter\Repository\ContactRepository;
use Pushword\Newsletter\Segment\SegmentException;
use Pushword\Newsletter\Segment\SegmentResolver;
use Pushword\Newsletter\Service\ContactManager;
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
    public function __construct(
        private readonly ContactRepository $contactRepository,
        private readonly AudienceRepository $audienceRepository,
        private readonly ContactManager $contactManager,
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
            $queryBuilder->andWhere('c.email LIKE :q OR c.name LIKE :q')
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
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $this->badRequest('Missing or invalid email');
        }

        $existing = $this->contactRepository->findOneByEmail($audience, $email);

        // An import of an already-consenting base skips the confirmation mail;
        // anything else follows the audience's own rule.
        $requireDoubleOptIn = 'subscribed' === ($data['status'] ?? null) ? false : null;

        $contact = $this->contactManager->subscribe(
            $audience,
            $email,
            \is_string($data['name'] ?? null) ? $data['name'] : null,
            \is_string($data['locale'] ?? null) ? $data['locale'] : null,
            [],
            \is_string($data['source'] ?? null) ? $data['source'] : 'api',
            null,
            null,
            $requireDoubleOptIn,
        );

        $this->apply($contact, $data);

        $violations = $this->validator->validate($contact);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
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

    private function doUpdate(Contact $contact, Request $request): JsonResponse
    {
        $this->apply($contact, $this->decodeJson($request));

        $violations = $this->validator->validate($contact);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $this->entityManager->flush();

        return $this->respond($this->toArray($contact));
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
            $contact->setName($data['name']);
        }

        if (\array_key_exists('locale', $data) && \is_string($data['locale'])) {
            $contact->setLocale($data['locale']);
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
            'audience' => $contact->getAudience()->getSlug(),
            'email' => $contact->getEmail(),
            'name' => $contact->getName(),
            'locale' => $contact->getLocale(),
            'status' => $contact->getStatusLabel(),
            'tags' => $contact->getTagList(),
            'customProperties' => $contact->customProperties,
            'source' => $contact->getSource(),
            'optinHost' => $contact->getOptinHost(),
            'createdAt' => $contact->createdAt?->format(DateTimeInterface::ATOM),
            'confirmedAt' => $contact->getConfirmedAt()?->format(DateTimeInterface::ATOM),
            'unsubscribedAt' => $contact->getUnsubscribedAt()?->format(DateTimeInterface::ATOM),
            'bouncedAt' => $contact->getBouncedAt()?->format(DateTimeInterface::ATOM),
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
                        'summary' => 'Create or update a contact, keyed on (audience, email)',
                        'responses' => ['200' => ['description' => 'Updated'], '201' => ['description' => 'Created']],
                    ],
                ],
                '/api/newsletter/contact/{id}' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'get' => ['summary' => 'Get a contact', 'responses' => ['200' => ['description' => 'OK']]],
                    'patch' => ['summary' => 'Update a contact; customProperties are merged, a null value removes a key', 'responses' => ['200' => ['description' => 'OK']]],
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
                            'email' => ['type' => 'string'],
                            'name' => ['type' => 'string'],
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
