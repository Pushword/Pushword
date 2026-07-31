<?php

namespace Pushword\Newsletter\Controller\Api;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Newsletter\Content\PageCriteria;
use Pushword\Newsletter\Content\PageMatcher;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\ContentTrigger;
use Pushword\Newsletter\Repository\AudienceRepository;
use Pushword\Newsletter\Repository\ContentTriggerLogRepository;
use Pushword\Newsletter\Repository\ContentTriggerRepository;
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
 * Content triggers over HTTP: wire a publication to a mail, and see what it
 * would catch before it catches anything.
 *
 * A `GET` on one reports both sides of the rule — how many pages are waiting and
 * how many contacts would receive the mail — because a trigger nobody can count
 * is one nobody switches on.
 */
#[IsGranted('ROLE_EDITOR')]
final class ContentTriggerApiController extends AbstractApiController
{
    public function __construct(
        private readonly ContentTriggerRepository $triggerRepository,
        private readonly ContentTriggerLogRepository $logRepository,
        private readonly AudienceRepository $audienceRepository,
        private readonly SegmentResolver $segmentResolver,
        private readonly PageMatcher $pageMatcher,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/newsletter/content-trigger', name: 'pushword_api_newsletter_content_trigger_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $pagination = $this->paginationParams($request);
        $queryBuilder = $this->triggerRepository->createQueryBuilder('t');

        if (null !== $request->query->get('audience')) {
            $audience = $this->audienceRepository->findOneBySlug($request->query->getString('audience'));
            if (! $audience instanceof Audience) {
                return $this->notFound('Audience not found');
            }

            $queryBuilder->andWhere('t.audience = :audience')->setParameter('audience', $audience);
        }

        if (null !== $request->query->get('enabled')) {
            $queryBuilder->andWhere('t.enabled = :enabled')
                ->setParameter('enabled', $request->query->getBoolean('enabled'));
        }

        $total = (int) (clone $queryBuilder)->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();
        $queryBuilder->orderBy('t.id', 'DESC')
            ->setFirstResult($pagination['offset'])
            ->setMaxResults($pagination['perPage']);

        /** @var list<ContentTrigger> $triggers */
        $triggers = $queryBuilder->getQuery()->getResult();

        return $this->respond($this->paginated(array_map($this->toArray(...), $triggers), $total, $pagination['page'], $pagination['perPage']));
    }

    #[Route('/api/newsletter/content-trigger', name: 'pushword_api_newsletter_content_trigger_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);

        $audience = $this->audienceRepository->findOneBySlug(\is_string($data['audience'] ?? null) ? $data['audience'] : '');
        if (! $audience instanceof Audience) {
            return $this->notFound('Audience not found');
        }

        $trigger = new ContentTrigger()->setAudience($audience);

        $error = $this->apply($trigger, $data);
        if (null !== $error) {
            return $error;
        }

        $violations = $this->validator->validate($trigger);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $this->entityManager->persist($trigger);
        $this->entityManager->flush();

        return $this->respond($this->toArray($trigger), Response::HTTP_CREATED);
    }

    #[Route('/api/newsletter/content-trigger/{id}', name: 'pushword_api_newsletter_content_trigger_item', requirements: ['id' => '\d+'], methods: ['GET', 'PATCH', 'DELETE'])]
    public function item(int $id, Request $request): JsonResponse
    {
        $trigger = $this->triggerRepository->find($id);
        if (! $trigger instanceof ContentTrigger) {
            return $this->notFound('Content trigger not found');
        }

        return match ($request->getMethod()) {
            'GET' => $this->respond($this->toArray($trigger, withProgress: true)),
            'PATCH' => $this->doUpdate($trigger, $request),
            default => $this->doDelete($trigger),
        };
    }

    private function doUpdate(ContentTrigger $trigger, Request $request): JsonResponse
    {
        $error = $this->apply($trigger, $this->decodeJson($request));
        if (null !== $error) {
            return $error;
        }

        $violations = $this->validator->validate($trigger);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $this->entityManager->flush();

        return $this->respond($this->toArray($trigger, withProgress: true));
    }

    private function doDelete(ContentTrigger $trigger): JsonResponse
    {
        // Log rows point at the trigger without being mapped back from it, so
        // nothing cascades on the ORM side. Dropping them here keeps the
        // deletion whole on SQLite, which does not apply the database's own.
        // The campaigns they produced are left alone: they are ordinary
        // campaigns, and some of them have been sent.
        foreach ($this->logRepository->findBy(['trigger' => $trigger]) as $log) {
            $this->entityManager->remove($log);
        }

        $this->entityManager->remove($trigger);
        $this->entityManager->flush();

        return $this->noContent();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return JsonResponse|null the error response, or null when everything applied
     */
    private function apply(ContentTrigger $trigger, array $data): ?JsonResponse
    {
        if (\array_key_exists('name', $data) && \is_string($data['name'])) {
            $trigger->setName($data['name']);
        }

        if (\array_key_exists('enabled', $data) && \is_bool($data['enabled'])) {
            $trigger->setEnabled($data['enabled']);
        }

        if (\array_key_exists('subjectTemplate', $data) && \is_string($data['subjectTemplate'])) {
            $trigger->setSubjectTemplate($data['subjectTemplate']);
        }

        if (\array_key_exists('bodyTemplate', $data) && \is_string($data['bodyTemplate'])) {
            $trigger->setBodyTemplate($data['bodyTemplate']);
        }

        if (\array_key_exists('delayMinutes', $data) && \is_int($data['delayMinutes'])) {
            $trigger->setDelayMinutes($data['delayMinutes']);
        }

        if (\array_key_exists('hosts', $data)) {
            if (! \is_array($data['hosts'])) {
                return $this->badRequest('hosts must be a list');
            }

            $trigger->setHosts(array_values(array_filter($data['hosts'], is_string(...))));
        }

        if (\array_key_exists('triggerFrom', $data)) {
            $triggerFrom = $this->parseDate(\is_string($data['triggerFrom']) ? $data['triggerFrom'] : null);
            if (! $triggerFrom instanceof DateTimeImmutable) {
                return $this->badRequest('Invalid triggerFrom');
            }

            $trigger->setTriggerFrom($triggerFrom);
        }

        return $this->applyCriteria($trigger, $data);
    }

    /** @param array<string, mixed> $data */
    private function applyCriteria(ContentTrigger $trigger, array $data): ?JsonResponse
    {
        if (\array_key_exists('pageWhen', $data)) {
            try {
                PageCriteria::validate($data['pageWhen']);
            } catch (SegmentException $segmentException) {
                return $this->badRequest('pageWhen: '.$segmentException->getMessage());
            }

            /** @var array<mixed> $pageWhen */
            $pageWhen = $data['pageWhen'];
            $trigger->setPageWhen($pageWhen);
        }

        if (\array_key_exists('segment', $data)) {
            try {
                SegmentCriteria::validate($data['segment']);
            } catch (SegmentException $segmentException) {
                return $this->badRequest('segment: '.$segmentException->getMessage());
            }

            /** @var array<mixed> $segment */
            $segment = $data['segment'];
            $trigger->setSegment($segment);
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
    private function toArray(ContentTrigger $trigger, bool $withProgress = false): array
    {
        $audience = $trigger->getAudience();

        $payload = [
            'id' => $trigger->id,
            'audience' => $audience?->getSlug(),
            'name' => $trigger->getName(),
            'enabled' => $trigger->isEnabled(),
            'hosts' => $trigger->getHosts(),
            'pageWhen' => $trigger->getPageWhen(),
            'segment' => $trigger->getSegment(),
            'delayMinutes' => $trigger->getDelayMinutes(),
            'subjectTemplate' => $trigger->getSubjectTemplate(),
            'bodyTemplate' => $trigger->getBodyTemplate(),
            'triggerFrom' => $trigger->getTriggerFrom()->format(DateTimeInterface::ATOM),
        ];

        if (! $withProgress) {
            return $payload;
        }

        $payload['campaignsCreated'] = $this->logRepository->countFor($trigger);

        try {
            $payload['waitingPages'] = $this->pageMatcher->count($trigger, new DateTimeImmutable());
        } catch (SegmentException) {
            $payload['waitingPages'] = null;
        }

        if ($audience instanceof Audience) {
            try {
                $payload['matchingContacts'] = $this->segmentResolver->count($audience, $trigger->getSegment());
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
                '/api/newsletter/content-trigger' => [
                    'get' => [
                        'summary' => 'List content triggers',
                        'parameters' => [
                            ['name' => 'audience', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'enabled', 'in' => 'query', 'schema' => ['type' => 'boolean']],
                        ],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                    'post' => ['summary' => 'Create a content trigger', 'responses' => ['201' => ['description' => 'Created']]],
                ],
                '/api/newsletter/content-trigger/{id}' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'get' => ['summary' => 'Get a content trigger, with the pages waiting for it and how many contacts its segment reaches', 'responses' => ['200' => ['description' => 'OK']]],
                    'patch' => ['summary' => 'Update a content trigger', 'responses' => ['200' => ['description' => 'OK']]],
                    'delete' => ['summary' => 'Delete a content trigger; the campaigns it created are kept', 'responses' => ['204' => ['description' => 'Deleted']]],
                ],
            ],
            'components' => [
                'schemas' => [
                    'NewsletterContentTrigger' => [
                        'type' => 'object',
                        'properties' => [
                            'audience' => ['type' => 'string'],
                            'name' => ['type' => 'string'],
                            'enabled' => ['type' => 'boolean'],
                            'hosts' => ['type' => 'array', 'description' => 'Pushword hosts to watch; empty watches every one', 'items' => ['type' => 'string']],
                            'pageWhen' => ['description' => 'Page criteria (slug, template, tag, parent, ancestor, prop.<key>), ANDed; {"any": [...]} ORs them instead. A child may be a group of its own. Empty matches every published page', 'oneOf' => [['type' => 'array', 'items' => ['type' => 'object']], ['type' => 'object']]],
                            'segment' => ['description' => 'Contact criteria for the mail that goes out, ANDed; {"any": [...]} ORs them instead', 'oneOf' => [['type' => 'array', 'items' => ['type' => 'object']], ['type' => 'object']]],
                            'delayMinutes' => ['type' => 'integer', 'description' => 'Wait after publication; 1440 is the day after'],
                            'subjectTemplate' => ['type' => 'string', 'description' => 'May quote {{ page.h1 }}, {{ page.excerpt }}, {{ page.url }}, {{ page.mainImage }}'],
                            'bodyTemplate' => ['type' => 'string', 'description' => 'Markdown, same placeholders'],
                            'triggerFrom' => ['type' => 'string', 'format' => 'date-time', 'description' => 'Pages published before this never trigger; defaults to now'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
