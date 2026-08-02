<?php

namespace Pushword\Newsletter\Controller;

use DateTimeImmutable;
use Pushword\Newsletter\Criteria\AbstractCriteria;
use Pushword\Newsletter\Criteria\CriteriaVocabulary;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Repository\AudienceRepository;
use Pushword\Newsletter\Repository\AutomationRepository;
use Pushword\Newsletter\Segment\SegmentCriteria;
use Pushword\Newsletter\Segment\SegmentException;
use Pushword\Newsletter\Segment\SegmentResolver;
use Pushword\Newsletter\Trigger\TriggerOccurrence;
use Pushword\Newsletter\Trigger\TriggerSourceRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * What the admin's condition builder needs and a textarea does not: which fields
 * and operators the rule being edited may use, and what it would catch.
 *
 * Both answers are computed from the automation's *unsaved* form — the source
 * picked a second ago, the rule half written — which is the whole point: a rule
 * one has to save to find out about is one that gets saved wrong.
 *
 * Neither endpoint writes anything: {@see self::preview()} lends the automation
 * the rule for the length of the count and gives it its own back, so counting a
 * rule can never become a way of storing it.
 */
#[IsGranted('ROLE_EDITOR')]
final class CriteriaController extends AbstractController
{
    /** Enough matches to tell a working rule from a runaway one. */
    private const int PREVIEW_LIMIT = 5;

    /**
     * Which rule is being edited, and so which vocabulary it is written in: what
     * the source watches, or who receives — `recipientWhen`, `stopWhen` and a
     * campaign's segment are contact criteria whatever the source is.
     */
    public const string SIDE_TRIGGER = 'trigger';

    public const string SIDE_CONTACT = 'contact';

    public function __construct(
        private readonly CriteriaVocabulary $vocabulary,
        private readonly TriggerSourceRegistry $sources,
        private readonly SegmentResolver $segmentResolver,
        private readonly AutomationRepository $automationRepository,
        private readonly AudienceRepository $audienceRepository,
    ) {
    }

    #[Route('/admin/newsletter/criteria/vocabulary', name: 'pushword_newsletter_criteria_vocabulary', methods: ['GET'])]
    public function vocabulary(Request $request): JsonResponse
    {
        $criteria = $this->criteriaFor(
            $request->query->getString('side'),
            $request->query->getString('source'),
        );

        if (null === $criteria) {
            return $this->cannotRead($request->query->getString('side'), $request->query->getString('source'));
        }

        /** @var string[] $hosts */
        $hosts = $request->query->all('hosts');

        return new JsonResponse($this->vocabulary->describe($criteria, $hosts));
    }

    /**
     * What the rule in the form would catch right now.
     *
     * A malformed rule is an answer, not a failure: it comes back as the message
     * the validator would have given on save, which is what the editor is asking
     * about while typing.
     */
    #[Route('/admin/newsletter/criteria/preview', name: 'pushword_newsletter_criteria_preview', methods: ['POST'])]
    public function preview(Request $request): JsonResponse
    {
        $decoded = json_decode($request->getContent(), true);
        /** @var array<string, mixed> $payload */
        $payload = \is_array($decoded) ? $decoded : [];

        $side = \is_string($payload['side'] ?? null) ? $payload['side'] : '';
        $source = \is_string($payload['source'] ?? null) ? $payload['source'] : '';
        $criteria = $this->criteriaFor($side, $source);

        if (null === $criteria) {
            return $this->cannotRead($side, $source);
        }

        try {
            $rule = $criteria::fromJson(\is_string($payload['rule'] ?? null) ? $payload['rule'] : '');

            return new JsonResponse(self::SIDE_TRIGGER === $side
                ? $this->countTrigger($source, $rule, $payload)
                : $this->countContacts($rule, $payload));
        } catch (SegmentException $segmentException) {
            return new JsonResponse(['error' => $segmentException->getMessage()]);
        }
    }

    /**
     * A source counts against an automation — it is what the rule is scoped by,
     * and what the already-handled markers hang off — so the one being edited is
     * lent the unsaved values for the length of the count and given its own back
     * afterwards. Nothing is ever flushed, and the restore runs whatever happens,
     * so the entity a later request reads is the stored one.
     *
     * An automation that has never been saved has no identity to subtract
     * markers by, and says so rather than answering for a rule it cannot scope.
     *
     * @param array<mixed>         $rule
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     *
     * @throws SegmentException
     */
    private function countTrigger(string $source, array $rule, array $payload): array
    {
        $triggerSource = $this->sources->find($source);
        $automation = \is_int($payload['automation'] ?? null)
            ? $this->automationRepository->find($payload['automation'])
            : null;

        if (null === $triggerSource || ! $automation instanceof Automation) {
            return ['count' => null, 'saveFirst' => true];
        }

        $stored = [$automation->source, $automation->hosts, $automation->triggerWhen, $automation->activeFrom];

        $automation->source = $source;
        $automation->triggerWhen = $rule;
        $automation->hosts = \is_array($payload['hosts'] ?? null)
            ? array_values(array_filter($payload['hosts'], is_string(...)))
            : [];

        // The start date is what makes a fresh automation preview as zero: it
        // defaults to its creation, and nothing has happened since. Lifting it
        // answers "would this rule ever catch anything", which is the question
        // being asked while the rule is being written.
        if (true === ($payload['sinceAll'] ?? false)) {
            $automation->activeFrom = new DateTimeImmutable('@0');
        }

        $now = new DateTimeImmutable();

        try {
            return [
                'count' => $triggerSource->count($automation, $now),
                'samples' => array_map(
                    static fn (TriggerOccurrence $occurrence): string => $occurrence->contact->email
                        ?? $occurrence->slug
                        ?? '#'.$occurrence->subjectId,
                    $triggerSource->occurrences($automation, $now, self::PREVIEW_LIMIT),
                ),
            ];
        } finally {
            [$automation->source, $automation->hosts, $automation->triggerWhen, $automation->activeFrom] = $stored;
        }
    }

    /**
     * @param array<mixed>         $rule
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     *
     * @throws SegmentException
     */
    private function countContacts(array $rule, array $payload): array
    {
        $audience = \is_int($payload['audience'] ?? null)
            ? $this->audienceRepository->find($payload['audience'])
            : null;

        if (! $audience instanceof Audience) {
            return ['count' => null, 'needsAudience' => true];
        }

        return [
            'count' => $this->segmentResolver->count($audience, $rule),
            'samples' => array_map(
                static fn (Contact $contact): string => $contact->email,
                $this->segmentResolver->contacts($audience, $rule, self::PREVIEW_LIMIT),
            ),
        ];
    }

    /**
     * Which vocabulary the rule is written in: the source's own for the trigger,
     * the contact language for the two rules that select who receives.
     *
     * @return class-string<AbstractCriteria>|null null when nothing answers to the source's name
     */
    private function criteriaFor(string $side, string $source): ?string
    {
        return match ($side) {
            self::SIDE_CONTACT => SegmentCriteria::class,
            self::SIDE_TRIGGER => $this->sources->find($source)?->criteria(),
            default => null,
        };
    }

    private function cannotRead(string $side, string $source): JsonResponse
    {
        return new JsonResponse(
            ['error' => self::SIDE_TRIGGER === $side
                ? \sprintf('No trigger source is named "%s". Known sources: %s.', $source, implode(', ', $this->sources->names()))
                : \sprintf('Unknown criteria side "%s".', $side)],
            Response::HTTP_BAD_REQUEST,
        );
    }
}
