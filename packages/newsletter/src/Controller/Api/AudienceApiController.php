<?php

namespace Pushword\Newsletter\Controller\Api;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Repository\AudienceRepository;
use Pushword\Newsletter\Repository\ContactRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Audiences over HTTP: the list every other endpoint names by slug, so a site
 * can be set up without opening the admin first.
 *
 * The slug is the identity a form, a contact and a campaign all quote, so it is
 * fixed at creation: renaming it belongs where the templates quoting it can be
 * fixed in the same sitting.
 */
#[IsGranted('ROLE_EDITOR')]
final class AudienceApiController extends AbstractApiController
{
    public function __construct(
        private readonly AudienceRepository $audienceRepository,
        private readonly ContactRepository $contactRepository,
        private readonly SiteRegistry $siteRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/newsletter/audience', name: 'pushword_api_newsletter_audience_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $pagination = $this->paginationParams($request);
        $queryBuilder = $this->audienceRepository->createQueryBuilder('a');

        if (null !== $request->query->get('host')) {
            $queryBuilder->andWhere('a.mainHost = :host')->setParameter('host', $request->query->getString('host'));
        }

        $total = (int) (clone $queryBuilder)->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
        $queryBuilder->orderBy('a.slug', 'ASC')
            ->setFirstResult($pagination['offset'])
            ->setMaxResults($pagination['perPage']);

        /** @var list<Audience> $audiences */
        $audiences = $queryBuilder->getQuery()->getResult();

        return $this->respond($this->paginated(array_map($this->toArray(...), $audiences), $total, $pagination['page'], $pagination['perPage']));
    }

    #[Route('/api/newsletter/audience', name: 'pushword_api_newsletter_audience_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);

        $audience = new Audience()->setSlug(\is_string($data['slug'] ?? null) ? $data['slug'] : '');

        $error = $this->apply($audience, $data);
        if (null !== $error) {
            return $error;
        }

        $violations = $this->validator->validate($audience);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        // The slug is unique in the database; saying so is better than the 500
        // the constraint would raise.
        if ($this->audienceRepository->findOneBySlug($audience->getSlug()) instanceof Audience) {
            return $this->respond(['error' => 'An audience already uses this slug'], Response::HTTP_CONFLICT);
        }

        $this->entityManager->persist($audience);
        $this->entityManager->flush();

        return $this->respond($this->toArray($audience), Response::HTTP_CREATED);
    }

    #[Route('/api/newsletter/audience/{slug}', name: 'pushword_api_newsletter_audience_item', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET', 'PATCH', 'DELETE'])]
    public function item(string $slug, Request $request): JsonResponse
    {
        $audience = $this->audienceRepository->findOneBySlug($slug);
        if (! $audience instanceof Audience) {
            return $this->notFound('Audience not found');
        }

        return match ($request->getMethod()) {
            'GET' => $this->respond($this->toArray($audience, withContacts: true)),
            'PATCH' => $this->doUpdate($audience, $request),
            default => $this->doDelete($audience),
        };
    }

    private function doUpdate(Audience $audience, Request $request): JsonResponse
    {
        $error = $this->apply($audience, $this->decodeJson($request));
        if (null !== $error) {
            return $error;
        }

        $violations = $this->validator->validate($audience);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $this->entityManager->flush();

        return $this->respond($this->toArray($audience, withContacts: true));
    }

    private function doDelete(Audience $audience): JsonResponse
    {
        // Contacts, campaigns and automations hang off the audience by a
        // database cascade: deleting one that still holds contacts would drop
        // their consent records without ever naming them.
        $contacts = $this->contactRepository->count(['audience' => $audience]);
        if ($contacts > 0) {
            return $this->respond([
                'error' => 'Audience still has contacts',
                'contacts' => $contacts,
            ], Response::HTTP_CONFLICT);
        }

        $this->entityManager->remove($audience);
        $this->entityManager->flush();

        return $this->noContent();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return JsonResponse|null the error response, or null when everything applied
     */
    private function apply(Audience $audience, array $data): ?JsonResponse
    {
        if (\array_key_exists('mainHost', $data) && \is_string($data['mainHost'])) {
            // An unknown host resolves to the default site, so the confirm and
            // unsubscribe links would quietly point at another brand. An alias
            // is stored as the main host it belongs to: audiences are compared
            // on that string to find the same person on a sibling list.
            $mainHost = $this->siteRegistry->findHost($data['mainHost']);
            if ('' === $mainHost) {
                return $this->badRequest('Unknown host: '.$data['mainHost']);
            }

            $audience->setMainHost($mainHost);
        }

        if (\array_key_exists('name', $data) && \is_string($data['name'])) {
            $audience->setName($data['name']);
        }

        if (\array_key_exists('fromName', $data) && \is_string($data['fromName'])) {
            $audience->setFromName($data['fromName']);
        }

        if (\array_key_exists('fromEmail', $data) && \is_string($data['fromEmail'])) {
            $audience->setFromEmail($data['fromEmail']);
        }

        if (\array_key_exists('replyTo', $data)) {
            $audience->setReplyTo(\is_string($data['replyTo']) ? $data['replyTo'] : null);
        }

        if (\array_key_exists('requireDoubleOptIn', $data) && \is_bool($data['requireDoubleOptIn'])) {
            $audience->setRequireDoubleOptIn($data['requireDoubleOptIn']);
        }

        if (\array_key_exists('interests', $data) && \is_array($data['interests'])) {
            $audience->setInterests(array_values(array_filter($data['interests'], is_string(...))));
        }

        if (\array_key_exists('rateSeconds', $data) && \is_int($data['rateSeconds'])) {
            $audience->setRateSeconds($data['rateSeconds']);
        }

        if (\array_key_exists('utmSource', $data)) {
            $audience->setUtmSource(\is_string($data['utmSource']) ? $data['utmSource'] : null);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function toArray(Audience $audience, bool $withContacts = false): array
    {
        $payload = [
            'id' => $audience->id,
            'slug' => $audience->getSlug(),
            'name' => $audience->getName(),
            'mainHost' => $audience->getMainHost(),
            'fromName' => $audience->getFromName(),
            'fromEmail' => $audience->getFromEmail(),
            'replyTo' => $audience->getReplyTo(),
            'requireDoubleOptIn' => $audience->requireDoubleOptIn(),
            'interests' => $audience->getInterests(),
            'rateSeconds' => $audience->getRateSeconds(),
            'utmSource' => $audience->getUtmSource(),
            'createdAt' => $audience->createdAt?->format(DateTimeInterface::ATOM),
        ];

        if ($withContacts) {
            $payload['contacts'] = $this->contactRepository->countByStatus($audience);
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    public static function describe(): array
    {
        return [
            'paths' => [
                '/api/newsletter/audience' => [
                    'get' => [
                        'summary' => 'List audiences',
                        'parameters' => [
                            ['name' => 'host', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                    'post' => [
                        'summary' => 'Create an audience',
                        'responses' => [
                            '201' => ['description' => 'Created'],
                            '409' => ['description' => 'The slug is already taken'],
                        ],
                    ],
                ],
                '/api/newsletter/audience/{slug}' => [
                    'parameters' => [['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                    'get' => ['summary' => 'Get an audience, with its contact counts per status', 'responses' => ['200' => ['description' => 'OK']]],
                    'patch' => ['summary' => 'Update an audience; the slug is fixed at creation', 'responses' => ['200' => ['description' => 'OK']]],
                    'delete' => [
                        'summary' => 'Delete an audience; refused while it still holds contacts',
                        'responses' => [
                            '204' => ['description' => 'Deleted'],
                            '409' => ['description' => 'The audience still has contacts'],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'NewsletterAudience' => [
                        'type' => 'object',
                        'properties' => [
                            'slug' => ['type' => 'string', 'description' => 'Lowercase identity quoted by forms, contacts and campaigns; set once'],
                            'name' => ['type' => 'string'],
                            'mainHost' => ['type' => 'string', 'description' => 'A configured Pushword host; public links are built from it'],
                            'fromName' => ['type' => 'string'],
                            'fromEmail' => ['type' => 'string'],
                            'replyTo' => ['type' => 'string', 'nullable' => true],
                            'requireDoubleOptIn' => ['type' => 'boolean'],
                            'interests' => ['type' => 'array', 'description' => 'The only interest values the public subscribe form may write', 'items' => ['type' => 'string']],
                            'rateSeconds' => ['type' => 'integer', 'description' => 'Seconds between two mails of this audience'],
                            'utmSource' => ['type' => 'string', 'nullable' => true],
                        ],
                    ],
                ],
            ],
        ];
    }
}
