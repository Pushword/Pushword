<?php

namespace Pushword\Newsletter\Trigger;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Repository\ContactRepository;
use Pushword\Newsletter\Segment\CriteriaGroup;

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
        private ContactRepository $contactRepository,
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
        $segment = $this->segment($audience, $automation, $occurrence);

        foreach ($automation->getOrderedSteps() as $position => $step) {
            $campaign = new Campaign();
            $campaign->audience = $audience;
            $campaign->subject = $this->renderer->renderSubject($step->subject, $occurrence->placeholders);
            $campaign->bodyMarkdown = $this->renderer->render($step->bodyMarkdown, $occurrence->placeholders);
            $campaign->segment = $segment;
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
     * Who the broadcast is addressed to: the automation's own rule, narrowed to
     * the occurrence's language when the audience is read in more than one.
     *
     * `recipientWhen` is one rule for every occurrence, so seventeen locale
     * versions of an article — seventeen pages, seventeen occurrences — would
     * otherwise produce seventeen campaigns each broadcast to the whole
     * audience, and every reader would receive the same article seventeen
     * times, once per language.
     *
     * The condition is asked of the contacts rather than of the site's hosts,
     * because that is where the ambiguity actually is: an audience nobody has
     * subscribed to in a second language has nothing to disambiguate, keeps its
     * rule untouched, and starts being narrowed by itself on the day it does.
     *
     * It removes an obligation and imposes no model: one campaign per market
     * for editorial rather than technical reasons stays a matter of writing
     * `locale` into `recipientWhen` by hand.
     *
     * @return array<mixed>
     */
    private function segment(Audience $audience, Automation $automation, TriggerOccurrence $occurrence): array
    {
        $locale = $occurrence->locale;

        if (null === $locale || \count($this->contactRepository->localesIn($audience)) < 2) {
            return $automation->recipientWhen;
        }

        return CriteriaGroup::and($automation->recipientWhen, ['field' => 'locale', 'op' => '=', 'value' => $locale]);
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
