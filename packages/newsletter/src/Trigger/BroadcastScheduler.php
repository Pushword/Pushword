<?php

namespace Pushword\Newsletter\Trigger;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\Campaign;

/**
 * Turns an occurrence nobody is individually addressed by into scheduled
 * campaigns — one per step, dated from the occurrence rather than from the tick.
 *
 * It sends nothing itself. What it produces is an ordinary {@see Campaign}, and
 * the tick's existing arm/drain path takes it from there, so a triggered mail is
 * paced, resumable and reportable exactly like a hand-written one, and can be
 * read, edited or cancelled in the admin during the delay before it.
 *
 * The templates are rendered once, here. Later edits to the subject do not
 * rewrite a campaign that already exists: what went out has to be what the
 * reporting says went out, and half a delay window of drift is worse than a
 * slightly stale excerpt.
 */
final readonly class BroadcastScheduler
{
    public function __construct(
        private PlaceholderRenderer $renderer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<Campaign> the campaigns created, not yet flushed
     */
    public function schedule(Automation $automation, TriggerOccurrence $occurrence): array
    {
        $audience = $automation->audience;

        if (null === $audience) {
            return [];
        }

        $campaigns = [];

        foreach ($automation->getOrderedSteps() as $position => $step) {
            $campaign = new Campaign();
            $campaign->audience = $audience;
            $campaign->subject = $this->renderer->renderSubject($step->subject, $occurrence->placeholders);
            $campaign->bodyMarkdown = $this->renderer->render($step->bodyMarkdown, $occurrence->placeholders);
            $campaign->segment = $automation->recipientWhen;
            $campaign->slug = $this->slug($occurrence, $step->subject, $position);
            $campaign
                ->triggeredBy($automation, $occurrence->subjectId)
                ->schedule($occurrence->occurredAt->modify('+'.$automation->delayToStep($position).' minutes'));

            $this->entityManager->persist($campaign);
            $campaigns[] = $campaign;
        }

        return $campaigns;
    }

    /**
     * The subject's own name reads better in a report than a step's subject line,
     * and every step after the first is numbered so a sequence about one page
     * does not collapse into one `utm_campaign`.
     */
    private function slug(TriggerOccurrence $occurrence, string $fallback, int $position): string
    {
        $slug = $occurrence->slug ?? $fallback;

        return 0 === $position ? $slug : $slug.'-'.($position + 1);
    }
}
