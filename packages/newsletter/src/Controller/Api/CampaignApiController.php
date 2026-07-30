<?php

namespace Pushword\Newsletter\Controller\Api;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Repository\AudienceRepository;
use Pushword\Newsletter\Repository\CampaignRepository;
use Pushword\Newsletter\Segment\SegmentCriteria;
use Pushword\Newsletter\Segment\SegmentException;
use Pushword\Newsletter\Segment\SegmentResolver;
use Pushword\Newsletter\Service\CampaignSender;
use Pushword\Newsletter\Service\NewsletterMailer;
use Pushword\Newsletter\Utm\UtmTag;
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
        private readonly SegmentResolver $segmentResolver,
        private readonly CampaignSender $campaignSender,
        private readonly NewsletterMailer $mailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
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

        $audience = $this->audienceRepository->findOneBySlug(\is_string($data['audience'] ?? null) ? $data['audience'] : '');
        if (! $audience instanceof Audience) {
            return $this->notFound('Audience not found');
        }

        $campaign = new Campaign()->setAudience($audience);

        $error = $this->apply($campaign, $data);
        if (null !== $error) {
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

        $campaign->schedule($when);
        $this->entityManager->flush();

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

        $this->campaignSender->arm($campaign);

        return $this->respond($this->toArray($campaign));
    }

    #[Route('/api/newsletter/campaign/{id}/test', name: 'pushword_api_newsletter_campaign_test', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function test(int $id, Request $request): JsonResponse
    {
        $campaign = $this->campaignRepository->find($id);
        if (! $campaign instanceof Campaign) {
            return $this->notFound('Campaign not found');
        }

        $audience = $campaign->getAudience();
        if (! $audience instanceof Audience) {
            return $this->badRequest('Campaign has no audience');
        }

        $data = $this->decodeJson($request);
        $addresses = \is_array($data['emails'] ?? null) ? $data['emails'] : [];

        $sent = [];
        $failed = [];

        foreach ($addresses as $address) {
            if (! \is_string($address) || false === filter_var($address, \FILTER_VALIDATE_EMAIL)) {
                $failed[] = \is_string($address) ? $address : '';

                continue;
            }

            try {
                $this->mailer->sendTest($audience, $campaign->getSubject(), $campaign->getBodyMarkdown(), $campaign->getPreheader(), $address, UtmTag::forCampaign($campaign));
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
        if (null !== $error) {
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
        if (\array_key_exists('subject', $data) && \is_string($data['subject'])) {
            $campaign->setSubject($data['subject']);
        }

        if (\array_key_exists('slug', $data)) {
            $campaign->setSlug(\is_string($data['slug']) ? $data['slug'] : null);
        }

        if (\array_key_exists('preheader', $data)) {
            $campaign->setPreheader(\is_string($data['preheader']) ? $data['preheader'] : null);
        }

        if (\array_key_exists('bodyMarkdown', $data) && \is_string($data['bodyMarkdown'])) {
            $campaign->setBodyMarkdown($data['bodyMarkdown']);
        }

        if (\array_key_exists('rateSeconds', $data)) {
            $campaign->setRateSeconds(\is_int($data['rateSeconds']) ? $data['rateSeconds'] : null);
        }

        if (\array_key_exists('segment', $data)) {
            try {
                SegmentCriteria::validate($data['segment']);
            } catch (SegmentException $segmentException) {
                return $this->badRequest($segmentException->getMessage());
            }

            /** @var array<mixed> $segment */
            $segment = $data['segment'];
            $campaign->setSegment($segment);
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
    private function toArray(Campaign $campaign, bool $withEstimate = false): array
    {
        $audience = $campaign->getAudience();

        $payload = [
            'id' => $campaign->id,
            'audience' => $audience?->getSlug(),
            'subject' => $campaign->getSubject(),
            'slug' => $campaign->getSlug(),
            'preheader' => $campaign->getPreheader(),
            'bodyMarkdown' => $campaign->getBodyMarkdown(),
            'segment' => $campaign->getSegment(),
            'status' => $campaign->getStatusLabel(),
            'rateSeconds' => $campaign->getEffectiveRateSeconds(),
            'scheduledAt' => $campaign->getScheduledAt()?->format(DateTimeInterface::ATOM),
            'sentAt' => $campaign->getSentAt()?->format(DateTimeInterface::ATOM),
            'stats' => [
                'recipients' => $campaign->getRecipientCount(),
                'sent' => $campaign->getSentCount(),
                'failed' => $campaign->getFailedCount(),
                'unsubscribed' => $campaign->getUnsubCount(),
                'bounced' => $campaign->getBounceCount(),
            ],
        ];

        if ($withEstimate && null !== $audience && $campaign->isDraft()) {
            try {
                $payload['estimatedRecipients'] = $this->segmentResolver->count($audience, $campaign->getSegment());
            } catch (SegmentException) {
                $payload['estimatedRecipients'] = null;
            }
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
                    'get' => ['summary' => 'Get a campaign, with the size of its segment while it is a draft', 'responses' => ['200' => ['description' => 'OK']]],
                    'patch' => ['summary' => 'Update a draft campaign', 'responses' => ['200' => ['description' => 'OK']]],
                    'delete' => ['summary' => 'Delete a campaign', 'responses' => ['204' => ['description' => 'Deleted']]],
                ],
                '/api/newsletter/campaign/{id}/schedule' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'post' => ['summary' => 'Arm a draft for a date', 'responses' => ['200' => ['description' => 'OK']]],
                ],
                '/api/newsletter/campaign/{id}/send' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'post' => ['summary' => 'Freeze recipients and open the send; mails go out from pw:newsletter:tick', 'responses' => ['200' => ['description' => 'OK']]],
                ],
                '/api/newsletter/campaign/{id}/test' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'post' => ['summary' => 'Mail a preview to arbitrary addresses; touches no contact and no counter', 'responses' => ['200' => ['description' => 'OK']]],
                ],
            ],
            'components' => [
                'schemas' => [
                    'NewsletterCampaign' => [
                        'type' => 'object',
                        'properties' => [
                            'audience' => ['type' => 'string'],
                            'subject' => ['type' => 'string'],
                            'slug' => ['type' => 'string', 'description' => 'utm_campaign value; derived from the subject when omitted'],
                            'preheader' => ['type' => 'string'],
                            'bodyMarkdown' => ['type' => 'string'],
                            'segment' => ['description' => 'Contact criteria, ANDed; {"any": [...]} ORs them instead', 'oneOf' => [['type' => 'array', 'items' => ['type' => 'object']], ['type' => 'object']]],
                            'rateSeconds' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
