<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Controller\Api;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use LogicException;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Core\Service\EditorialTimezone;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Enum\EditorialStage;
use Pushword\Newsletter\Event\CampaignIssue;
use Pushword\Newsletter\Repository\AudienceRepository;
use Pushword\Newsletter\Repository\CampaignRepository;
use Pushword\Newsletter\Repository\ClickEventRepository;
use Pushword\Newsletter\Segment\SegmentCriteria;
use Pushword\Newsletter\Segment\SegmentException;
use Pushword\Newsletter\Segment\SegmentResolver;
use Pushword\Newsletter\Service\CampaignSender;
use Pushword\Newsletter\Service\NewsletterMailer;
use Pushword\Newsletter\Service\SenderProfileRegistry;
use Pushword\Newsletter\Utm\UtmTag;
use Pushword\Newsletter\Validation\CampaignPreflight;
use Pushword\Newsletter\Validation\CampaignValidationFailed;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

/**
 * Campaigns over HTTP: author a broadcast, check how many it would reach, send
 * or schedule it.
 *
 * A campaign is only editable while it is a draft. Once armed, its recipient
 * rows exist and changing the body would mean two different mails going out
 * under one campaign's reporting.
 */
#[IsGranted('ROLE_EDITOR')]
final class CampaignApiController extends AbstractApiController
{
    public function __construct(
        private readonly CampaignRepository $campaignRepository,
        private readonly AudienceRepository $audienceRepository,
        private readonly ClickEventRepository $clickEventRepository,
        private readonly SegmentResolver $segmentResolver,
        private readonly CampaignSender $campaignSender,
        private readonly NewsletterMailer $mailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly EditorialTimezone $timezone,
        private readonly CampaignPreflight $preflight,
        private readonly SenderProfileRegistry $senderProfiles,
    ) {
    }

    #[Route('/api/newsletter/campaign', name: 'pushword_api_newsletter_campaign_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $pagination = $this->paginationParams($request);
        $queryBuilder = $this->campaignRepository->createQueryBuilder('c');

        if (null !== $request->query->get('audience')) {
            $audience = $this->audienceRepository->findOneBySlug($request->query->getString('audience'));
            if (! $audience instanceof Audience) {
                return $this->notFound('Audience not found');
            }

            $queryBuilder->andWhere('c.audience = :audience')->setParameter('audience', $audience);
        }

        if (null !== $request->query->get('status')) {
            $queryBuilder->andWhere('c.status = :status')->setParameter('status', $request->query->getString('status'));
        }

        $total = (int) (clone $queryBuilder)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        $queryBuilder->orderBy('c.id', 'DESC')
            ->setFirstResult($pagination['offset'])
            ->setMaxResults($pagination['perPage']);

        /** @var list<Campaign> $campaigns */
        $campaigns = $queryBuilder->getQuery()->getResult();

        return $this->respond($this->paginated(array_map($this->toArray(...), $campaigns), $total, $pagination['page'], $pagination['perPage']));
    }

    #[Route('/api/newsletter/campaign', name: 'pushword_api_newsletter_campaign_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);

        $campaign = new Campaign();

        $error = $this->apply($campaign, $data);
        if ($error instanceof JsonResponse) {
            return $error;
        }

        $violations = $this->validator->validate($campaign);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $this->entityManager->persist($campaign);
        $this->entityManager->flush();

        return $this->respond($this->toArray($campaign), Response::HTTP_CREATED);
    }

    #[Route('/api/newsletter/campaign/{id}', name: 'pushword_api_newsletter_campaign_item', requirements: ['id' => '\d+'], methods: ['GET', 'PATCH', 'DELETE'])]
    public function item(int $id, Request $request): JsonResponse
    {
        $campaign = $this->campaignRepository->find($id);
        if (! $campaign instanceof Campaign) {
            return $this->notFound('Campaign not found');
        }

        return match ($request->getMethod()) {
            'GET' => $this->respond($this->toArray($campaign, withEstimate: true)),
            'PATCH' => $this->doUpdate($campaign, $request),
            default => $this->doDelete($campaign),
        };
    }

    #[Route('/api/newsletter/campaign/{id}/duplicate', name: 'pushword_api_newsletter_campaign_duplicate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function duplicate(int $id): JsonResponse
    {
        $original = $this->campaignRepository->find($id);
        if (! $original instanceof Campaign) {
            return $this->notFound('Campaign not found');
        }

        $copy = $original->duplicate();
        $this->entityManager->persist($copy);
        $this->entityManager->flush();

        return $this->respond($this->toArray($copy), Response::HTTP_CREATED);
    }

    #[Route('/api/newsletter/campaign/{id}/check', name: 'pushword_api_newsletter_campaign_check', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function check(int $id): JsonResponse
    {
        $campaign = $this->campaignRepository->find($id);
        if (! $campaign instanceof Campaign) {
            return $this->notFound('Campaign not found');
        }

        return $this->respond(['issues' => array_map(static fn (CampaignIssue $issue): array => $issue->toArray(), $this->preflight->check($campaign))]);
    }

    #[Route('/api/newsletter/campaign/{id}/schedule', name: 'pushword_api_newsletter_campaign_schedule', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function schedule(int $id, Request $request): JsonResponse
    {
        $campaign = $this->campaignRepository->find($id);
        if (! $campaign instanceof Campaign) {
            return $this->notFound('Campaign not found');
        }

        if (! $campaign->isDraft()) {
            return $this->badRequest('Only a draft can be scheduled');
        }

        $data = $this->decodeJson($request);
        $when = $this->parseDate(\is_string($data['scheduledAt'] ?? null) ? $data['scheduledAt'] : null);

        if (! $when instanceof DateTimeImmutable) {
            return $this->badRequest('Missing or invalid scheduledAt');
        }

        try {
            $this->campaignSender->schedule($campaign, $when);
        } catch (CampaignValidationFailed $failure) {
            return $this->respond(['issues' => array_map(static fn (CampaignIssue $issue): array => $issue->toArray(), $failure->issues)], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (LogicException $logicException) {
            return $this->badRequest($logicException->getMessage());
        }

        return $this->respond($this->toArray($campaign));
    }

    #[Route('/api/newsletter/campaign/{id}/send', name: 'pushword_api_newsletter_campaign_send', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function send(int $id): JsonResponse
    {
        $campaign = $this->campaignRepository->find($id);
        if (! $campaign instanceof Campaign) {
            return $this->notFound('Campaign not found');
        }

        if (! $campaign->isDraft() && ! $campaign->isScheduled()) {
            return $this->badRequest('Campaign is '.$campaign->getStatusLabel());
        }

        try {
            $this->campaignSender->send($campaign);
        } catch (CampaignValidationFailed $failure) {
            return $this->respond(['issues' => array_map(static fn (CampaignIssue $issue): array => $issue->toArray(), $failure->issues)], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (LogicException $logicException) {
            return $this->badRequest($logicException->getMessage());
        }

        return $this->respond($this->toArray($campaign));
    }

    #[Route('/api/newsletter/campaign/{id}/test', name: 'pushword_api_newsletter_campaign_test', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function test(int $id, Request $request): JsonResponse
    {
        $campaign = $this->campaignRepository->find($id);
        if (! $campaign instanceof Campaign) {
            return $this->notFound('Campaign not found');
        }

        $audience = $campaign->audience;
        if (! $audience instanceof Audience) {
            return $this->badRequest('Campaign has no audience');
        }

        $data = $this->decodeJson($request);
        $addresses = \is_array($data['emails'] ?? null) ? $data['emails'] : [];

        // Which language to proofread; the campaign's own text when unsaid.
        $locale = \is_string($data['locale'] ?? null) ? trim($data['locale']) : '';
        $content = $campaign->contentFor($locale);

        $sent = [];
        $failed = [];

        foreach ($addresses as $address) {
            if (! \is_string($address) || false === filter_var($address, \FILTER_VALIDATE_EMAIL)) {
                $failed[] = \is_string($address) ? $address : '';

                continue;
            }

            try {
                $this->mailer->sendTest($audience, $content['subject'], $content['bodyMarkdown'], $content['preheader'], $address, UtmTag::forCampaign($campaign), $locale, $campaign->isTransactional(), $campaign->emailLayout, $this->senderProfiles->resolve($campaign));
                $sent[] = $address;
            } catch (Throwable $throwable) {
                $failed[] = $address.' ('.$throwable->getMessage().')';
            }
        }

        return $this->respond(['sent' => $sent, 'failed' => $failed]);
    }

    private function doUpdate(Campaign $campaign, Request $request): JsonResponse
    {
        if (! $campaign->isDraft()) {
            return $this->badRequest('Only a draft can be edited');
        }

        $error = $this->apply($campaign, $this->decodeJson($request));
        if ($error instanceof JsonResponse) {
            return $error;
        }

        $violations = $this->validator->validate($campaign);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $this->entityManager->flush();

        return $this->respond($this->toArray($campaign));
    }

    private function doDelete(Campaign $campaign): JsonResponse
    {
        $this->entityManager->remove($campaign);
        $this->entityManager->flush();

        return $this->noContent();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return JsonResponse|null the error response, or null when everything applied
     */
    private function apply(Campaign $campaign, array $data): ?JsonResponse
    {
        foreach (['workingTitle', 'internalNotes'] as $field) {
            if (isset($data[$field]) && \is_string($data[$field])) {
                $campaign->{$field} = $data[$field];
            }
        }

        if (\array_key_exists('audience', $data)) {
            $audience = \is_string($data['audience']) ? $this->audienceRepository->findOneBySlug($data['audience']) : null;
            if (null !== $data['audience'] && ! $audience instanceof Audience) {
                return $this->notFound('Audience not found');
            }

            $campaign->audience = $audience;
        }

        if (\array_key_exists('senderProfile', $data)) {
            $profile = $data['senderProfile'];
            if (null !== $profile && ! \in_array($profile, $this->senderProfiles->choices(), true)) {
                return $this->badRequest('Unknown sender profile');
            }

            $campaign->senderProfile = \is_string($profile) ? $profile : null;
        }

        if (\array_key_exists('editorialStage', $data)) {
            $stage = \is_string($data['editorialStage']) ? EditorialStage::tryFrom($data['editorialStage']) : null;
            if (null === $stage) {
                return $this->badRequest('Invalid editorialStage');
            }

            $campaign->editorialStage = $stage;
        }

        if (\array_key_exists('scheduledAt', $data)) {
            $when = \is_string($data['scheduledAt']) ? $this->parseDate($data['scheduledAt']) : null;
            if (null !== $data['scheduledAt'] && ! $when instanceof DateTimeImmutable) {
                return $this->badRequest('Invalid scheduledAt');
            }

            $campaign->scheduledAt = $when;
        }

        if (\array_key_exists('subject', $data) && \is_string($data['subject'])) {
            $campaign->subject = $data['subject'];
        }

        if (\array_key_exists('slug', $data)) {
            $campaign->slug = \is_string($data['slug']) ? $data['slug'] : null;
        }

        if (\array_key_exists('preheader', $data)) {
            $campaign->preheader = \is_string($data['preheader']) ? $data['preheader'] : null;
        }

        if (\array_key_exists('bodyMarkdown', $data) && \is_string($data['bodyMarkdown'])) {
            $campaign->bodyMarkdown = $data['bodyMarkdown'];
        }

        if (\array_key_exists('translations', $data) && \is_array($data['translations'])) {
            $campaign->mergeTranslations($data['translations']);
        }

        if (\array_key_exists('rateSeconds', $data)) {
            $campaign->rateSeconds = \is_int($data['rateSeconds']) ? $data['rateSeconds'] : null;
        }

        if (\array_key_exists('transactional', $data) && \is_bool($data['transactional'])) {
            $campaign->transactional = $data['transactional'];
        }

        if (\array_key_exists('segment', $data)) {
            try {
                SegmentCriteria::validate($data['segment']);
            } catch (SegmentException $segmentException) {
                return $this->badRequest($segmentException->getMessage());
            }

            /** @var array<mixed> $segment */
            $segment = $data['segment'];
            $campaign->segment = $segment;
        }

        return null;
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        try {
            return DateTimeImmutable::createFromMutable($this->timezone->parse($value));
        } catch (Exception) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private function toArray(Campaign $campaign, bool $withEstimate = false): array
    {
        $audience = $campaign->audience;

        $payload = [
            'id' => $campaign->id,
            'audience' => $audience?->slug,
            'subject' => $campaign->subject,
            'senderProfile' => $campaign->senderProfile,
            'workingTitle' => $campaign->workingTitle,
            'internalNotes' => $campaign->internalNotes,
            'editorialStage' => $campaign->editorialStage->value,
            'slug' => $campaign->slug,
            'preheader' => $campaign->preheader,
            'bodyMarkdown' => $campaign->bodyMarkdown,
            'translations' => $campaign->translations,
            'segment' => $campaign->segment,
            'status' => $campaign->getStatusLabel(),
            'sendIssues' => $campaign->sendIssues,
            'rateSeconds' => $campaign->getEffectiveRateSeconds(),
            'transactional' => $campaign->isTransactional(),
            'scheduledAt' => $campaign->scheduledAt?->format(DateTimeInterface::ATOM),
            'sentAt' => $campaign->sentAt?->format(DateTimeInterface::ATOM),
            'stats' => [
                'recipients' => $campaign->recipientCount,
                'sent' => $campaign->sentCount,
                'failed' => $campaign->failedCount,
                'unsubscribed' => $campaign->unsubCount,
                'bounced' => $campaign->bounceCount,
                'clicks' => $campaign->clickCount,
            ],
        ];

        if ($withEstimate && $audience instanceof Audience && $campaign->isDraft()) {
            try {
                $payload['estimatedRecipients'] = $this->segmentResolver->countMailable($audience, $campaign->segment);
            } catch (SegmentException) {
                $payload['estimatedRecipients'] = null;
            }
        }

        // Which links pulled — item reads only, a list would repeat the group-by
        // per row for numbers nobody asked the list for.
        if ($withEstimate) {
            $payload['clicksByUrl'] = $this->clickEventRepository->byUrl($campaign);
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    public static function describe(): array
    {
        return [
            'paths' => [
                '/api/newsletter/campaign' => [
                    'get' => [
                        'summary' => 'List campaigns',
                        'parameters' => [
                            ['name' => 'audience', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['draft', 'scheduled', 'sending', 'sent']]],
                        ],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                    'post' => ['summary' => 'Create a draft campaign', 'responses' => ['201' => ['description' => 'Created']]],
                ],
                '/api/newsletter/campaign/{id}' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'get' => ['summary' => 'Get a campaign, with the size of its segment while it is a draft and its clicks per link (clicksByUrl) once click tracking recorded any', 'responses' => ['200' => ['description' => 'OK']]],
                    'patch' => ['summary' => 'Update a draft campaign', 'responses' => ['200' => ['description' => 'OK']]],
                    'delete' => ['summary' => 'Delete a campaign', 'responses' => ['204' => ['description' => 'Deleted']]],
                ],
                '/api/newsletter/campaign/{id}/duplicate' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'post' => ['summary' => 'Copy authored settings into a new draft with independent delivery and tracking identity', 'responses' => ['201' => ['description' => 'Created'], '404' => ['description' => 'Campaign not found']]],
                ],
                '/api/newsletter/campaign/{id}/check' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'get' => ['summary' => 'Run shared preflight checks without sending; returns issues with code, message, field, severity, locale and block', 'responses' => ['200' => ['description' => 'Check report']]],
                ],
                '/api/newsletter/campaign/{id}/schedule' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'post' => ['summary' => 'Arm a draft for a date', 'responses' => ['200' => ['description' => 'OK'], '422' => ['description' => 'Preflight blocked; issues explain corrections']]],
                ],
                '/api/newsletter/campaign/{id}/send' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'post' => ['summary' => 'Authorize delivery: schedule at a future scheduledAt, otherwise start now; the tick delivers', 'responses' => ['200' => ['description' => 'OK'], '422' => ['description' => 'Preflight blocked; issues explain corrections']]],
                ],
                '/api/newsletter/campaign/{id}/test' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'post' => ['summary' => 'Mail a preview to arbitrary addresses; touches no contact and no counter. `locale` picks which translation to proofread', 'responses' => ['200' => ['description' => 'OK']]],
                ],
            ],
            'components' => [
                'schemas' => [
                    'NewsletterCampaign' => [
                        'type' => 'object',
                        'properties' => [
                            'audience' => ['type' => 'string', 'nullable' => true],
                            'senderProfile' => ['type' => 'string', 'nullable' => true, 'description' => 'Configured sender profile id, or null to inherit the audience'],
                            'scheduledAt' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true, 'description' => 'Planned date until Send explicitly authorizes delivery'],
                            'subject' => ['type' => 'string'],
                            'workingTitle' => ['type' => 'string', 'description' => 'Internal title; either this or subject is required to save a draft'],
                            'internalNotes' => ['type' => 'string', 'description' => 'Private preparation notes, never rendered in mail'],
                            'editorialStage' => ['type' => 'string', 'enum' => ['draft', 'review', 'ready'], 'description' => 'Optional preparation labels; never authorize sending'],
                            'slug' => ['type' => 'string', 'description' => 'utm_campaign value; derived from the subject when omitted'],
                            'preheader' => ['type' => 'string'],
                            'bodyMarkdown' => ['type' => 'string'],
                            'translations' => [
                                'type' => 'object',
                                'description' => 'The same campaign per locale — {"de": {"subject", "preheader", "bodyMarkdown"}}. Merged per locale on PATCH; a locale set to null drops it. A contact is sent their own locale, else its language part, else the campaign\'s own text',
                                'additionalProperties' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'subject' => ['type' => 'string'],
                                        'preheader' => ['type' => 'string'],
                                        'bodyMarkdown' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                            'segment' => ['description' => 'Contact criteria, ANDed; {"any": [...]} ORs them instead', 'oneOf' => [['type' => 'array', 'items' => ['type' => 'object']], ['type' => 'object']]],
                            'rateSeconds' => ['type' => 'integer'],
                            'transactional' => ['type' => 'boolean', 'description' => 'Sends this one with no unsubscribe link and no List-Unsubscribe header. Service messages only. Read back as the effective value, so an audience carrying the flag reports true here whatever was written'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
