<?php

namespace Pushword\Newsletter\Controller\Api;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\AutomationStep;
use Pushword\Newsletter\Repository\AudienceRepository;
use Pushword\Newsletter\Repository\AutomationRepository;
use Pushword\Newsletter\Repository\EnrollmentRepository;
use Pushword\Newsletter\Segment\SegmentCriteria;
use Pushword\Newsletter\Segment\SegmentException;
use Pushword\Newsletter\Segment\SegmentResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Automations over HTTP: build a drip, change its rules, watch it run.
 *
 * Steps travel as one array rather than as a sub-resource. They are ordered and
 * an automation without them does nothing, so a single round trip writes a
 * coherent sequence and the array's order is the sequence's order.
 */
#[IsGranted('ROLE_EDITOR')]
final class AutomationApiController extends AbstractApiController
{
    public function __construct(
        private readonly AutomationRepository $automationRepository,
        private readonly AudienceRepository $audienceRepository,
        private readonly EnrollmentRepository $enrollmentRepository,
        private readonly SegmentResolver $segmentResolver,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/newsletter/automation', name: 'pushword_api_newsletter_automation_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $pagination = $this->paginationParams($request);
        $queryBuilder = $this->automationRepository->createQueryBuilder('a');

        if (null !== $request->query->get('audience')) {
            $audience = $this->audienceRepository->findOneBySlug($request->query->getString('audience'));
            if (! $audience instanceof Audience) {
                return $this->notFound('Audience not found');
            }

            $queryBuilder->andWhere('a.audience = :audience')->setParameter('audience', $audience);
        }

        if (null !== $request->query->get('enabled')) {
            $queryBuilder->andWhere('a.enabled = :enabled')
                ->setParameter('enabled', $request->query->getBoolean('enabled'));
        }

        $total = (int) (clone $queryBuilder)->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
        $queryBuilder->orderBy('a.id', 'DESC')
            ->setFirstResult($pagination['offset'])
            ->setMaxResults($pagination['perPage']);

        /** @var list<Automation> $automations */
        $automations = $queryBuilder->getQuery()->getResult();

        return $this->respond($this->paginated(array_map($this->toArray(...), $automations), $total, $pagination['page'], $pagination['perPage']));
    }

    #[Route('/api/newsletter/automation', name: 'pushword_api_newsletter_automation_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);

        $audience = $this->audienceRepository->findOneBySlug(\is_string($data['audience'] ?? null) ? $data['audience'] : '');
        if (! $audience instanceof Audience) {
            return $this->notFound('Audience not found');
        }

        $automation = new Automation()->setAudience($audience);

        $error = $this->apply($automation, $data);
        if (null !== $error) {
            return $error;
        }

        $violations = $this->validator->validate($automation);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $this->entityManager->persist($automation);
        $this->entityManager->flush();

        return $this->respond($this->toArray($automation), Response::HTTP_CREATED);
    }

    #[Route('/api/newsletter/automation/{id}', name: 'pushword_api_newsletter_automation_item', requirements: ['id' => '\d+'], methods: ['GET', 'PATCH', 'DELETE'])]
    public function item(int $id, Request $request): JsonResponse
    {
        $automation = $this->automationRepository->find($id);
        if (! $automation instanceof Automation) {
            return $this->notFound('Automation not found');
        }

        return match ($request->getMethod()) {
            'GET' => $this->respond($this->toArray($automation, withProgress: true)),
            'PATCH' => $this->doUpdate($automation, $request),
            default => $this->doDelete($automation),
        };
    }

    private function doUpdate(Automation $automation, Request $request): JsonResponse
    {
        $error = $this->apply($automation, $this->decodeJson($request));
        if (null !== $error) {
            return $error;
        }

        $violations = $this->validator->validate($automation);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $this->entityManager->flush();

        return $this->respond($this->toArray($automation, withProgress: true));
    }

    private function doDelete(Automation $automation): JsonResponse
    {
        // Enrollments point at the automation without being mapped back from
        // it, so nothing cascades on the ORM side. Dropping them here rather
        // than through the database's own cascade keeps the deletion whole on
        // SQLite, and keeps an already-loaded enrollment from blocking it.
        foreach ($this->enrollmentRepository->findBy(['automation' => $automation]) as $enrollment) {
            $this->entityManager->remove($enrollment);
        }

        $this->entityManager->remove($automation);
        $this->entityManager->flush();

        return $this->noContent();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return JsonResponse|null the error response, or null when everything applied
     */
    private function apply(Automation $automation, array $data): ?JsonResponse
    {
        if (\array_key_exists('name', $data) && \is_string($data['name'])) {
            $automation->setName($data['name']);
        }

        if (\array_key_exists('enabled', $data) && \is_bool($data['enabled'])) {
            $automation->setEnabled($data['enabled']);
        }

        $error = $this->applyCriteria($automation, $data);
        if (null !== $error) {
            return $error;
        }

        if (\array_key_exists('enrollFrom', $data)) {
            $enrollFrom = $this->parseDate(\is_string($data['enrollFrom']) ? $data['enrollFrom'] : null);
            if (! $enrollFrom instanceof DateTimeImmutable) {
                return $this->badRequest('Invalid enrollFrom');
            }

            $automation->setEnrollFrom($enrollFrom);
        }

        if (! \array_key_exists('steps', $data)) {
            return null;
        }

        if (! \is_array($data['steps'])) {
            return $this->badRequest('steps must be a list');
        }

        return $this->applySteps($automation, $data['steps']);
    }

    /** @param array<string, mixed> $data */
    private function applyCriteria(Automation $automation, array $data): ?JsonResponse
    {
        foreach (['enrollWhen', 'stopWhen'] as $key) {
            if (! \array_key_exists($key, $data)) {
                continue;
            }

            try {
                SegmentCriteria::validate($data[$key]);
            } catch (SegmentException $segmentException) {
                return $this->badRequest($key.': '.$segmentException->getMessage());
            }

            /** @var array<mixed> $criteria */
            $criteria = $data[$key];

            if ('enrollWhen' === $key) {
                $automation->setEnrollWhen($criteria);
            } else {
                $automation->setStopWhen($criteria);
            }
        }

        return null;
    }

    /**
     * Steps are replaced wholesale: the array's order is the sequence's order,
     * so a caller reorders, adds or drops one by sending the list it wants.
     *
     * @param array<mixed> $steps
     */
    private function applySteps(Automation $automation, array $steps): ?JsonResponse
    {
        foreach ($automation->getSteps()->toArray() as $existing) {
            $automation->removeStep($existing);
        }

        foreach (array_values($steps) as $position => $step) {
            if (! \is_array($step) || ! \is_string($step['subject'] ?? null)) {
                return $this->badRequest(\sprintf('Step #%d needs a subject', $position));
            }

            $automation->addStep(new AutomationStep()
                ->setPosition($position)
                ->setDelayMinutes(\is_int($step['delayMinutes'] ?? null) ? $step['delayMinutes'] : 0)
                ->setSubject($step['subject'])
                ->setBodyMarkdown(\is_string($step['bodyMarkdown'] ?? null) ? $step['bodyMarkdown'] : ''));
        }

        return null;
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private function toArray(Automation $automation, bool $withProgress = false): array
    {
        $audience = $automation->getAudience();

        $payload = [
            'id' => $automation->id,
            'audience' => $audience?->getSlug(),
            'name' => $automation->getName(),
            'enabled' => $automation->isEnabled(),
            'enrollWhen' => $automation->getEnrollWhen(),
            'stopWhen' => $automation->getStopWhen(),
            'enrollFrom' => $automation->getEnrollFrom()->format(DateTimeInterface::ATOM),
            'steps' => array_map(static fn (AutomationStep $step): array => [
                'position' => $step->getPosition(),
                'delayMinutes' => $step->getDelayMinutes(),
                'subject' => $step->getSubject(),
                'bodyMarkdown' => $step->getBodyMarkdown(),
            ], $automation->getOrderedSteps()),
        ];

        if (! $withProgress) {
            return $payload;
        }

        $payload['stats'] = $this->enrollmentRepository->countByStatus($automation);

        if ($audience instanceof Audience) {
            try {
                $payload['matchingContacts'] = $this->segmentResolver->count($audience, $automation->getEnrollWhen());
            } catch (SegmentException) {
                $payload['matchingContacts'] = null;
            }
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    public static function describe(): array
    {
        return [
            'paths' => [
                '/api/newsletter/automation' => [
                    'get' => [
                        'summary' => 'List automations',
                        'parameters' => [
                            ['name' => 'audience', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'enabled', 'in' => 'query', 'schema' => ['type' => 'boolean']],
                        ],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                    'post' => ['summary' => 'Create an automation with its steps', 'responses' => ['201' => ['description' => 'Created']]],
                ],
                '/api/newsletter/automation/{id}' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'get' => ['summary' => 'Get an automation, with its enrollment counts and how many contacts match enrollWhen', 'responses' => ['200' => ['description' => 'OK']]],
                    'patch' => ['summary' => 'Update an automation; sending steps replaces the whole sequence', 'responses' => ['200' => ['description' => 'OK']]],
                    'delete' => ['summary' => 'Delete an automation and its enrollments', 'responses' => ['204' => ['description' => 'Deleted']]],
                ],
            ],
            'components' => [
                'schemas' => [
                    'NewsletterAutomation' => [
                        'type' => 'object',
                        'properties' => [
                            'audience' => ['type' => 'string'],
                            'name' => ['type' => 'string'],
                            'enabled' => ['type' => 'boolean'],
                            'enrollWhen' => ['description' => 'Segment criteria, ANDed; {"any": [...]} ORs them instead. Empty enrolls every subscribed contact', 'oneOf' => [['type' => 'array', 'items' => ['type' => 'object']], ['type' => 'object']]],
                            'stopWhen' => ['type' => 'array', 'description' => 'Re-checked before each step', 'items' => ['type' => 'object']],
                            'enrollFrom' => ['type' => 'string', 'format' => 'date-time', 'description' => 'Contacts registered before this are never enrolled; defaults to now'],
                            'steps' => [
                                'type' => 'array',
                                'description' => 'The whole sequence, in order',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'delayMinutes' => ['type' => 'integer', 'description' => 'After enrollment for the first step, after the previous one otherwise'],
                                        'subject' => ['type' => 'string'],
                                        'bodyMarkdown' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
