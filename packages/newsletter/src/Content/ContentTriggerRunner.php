<?php

namespace Pushword\Newsletter\Content;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\ContentTrigger;
use Pushword\Newsletter\Entity\ContentTriggerLog;
use Pushword\Newsletter\Repository\CampaignRepository;
use Pushword\Newsletter\Repository\ContentTriggerLogRepository;
use Pushword\Newsletter\Repository\ContentTriggerRepository;
use Pushword\Newsletter\Segment\SegmentException;

/**
 * Turns publications into scheduled campaigns.
 *
 * It creates nothing it sends: a matching page becomes an ordinary
 * {@see Campaign} dated `publishedAt + delay`, and the tick's existing arm/drain
 * path takes it from there. So a triggered mail is paced, resumable and
 * reportable exactly like a hand-written one, and can be read, edited or
 * cancelled in the admin during the delay.
 *
 * The body is rendered once, here. Later edits to the page do not rewrite a
 * campaign that already exists: what went out has to be what the reporting says
 * went out, and half a delay window of drift is worse than a slightly stale
 * excerpt.
 */
final readonly class ContentTriggerRunner
{
    public function __construct(
        private ContentTriggerRepository $triggerRepository,
        private ContentTriggerLogRepository $logRepository,
        private CampaignRepository $campaignRepository,
        private PageRepository $pageRepository,
        private PageMatcher $pageMatcher,
        private PagePlaceholders $placeholders,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /** @return array{triggered: int, cancelled: int} */
    public function run(DateTimeImmutable $now): array
    {
        $cancelled = $this->cancelUnpublished();

        $triggered = 0;
        foreach ($this->triggerRepository->findEnabled() as $trigger) {
            $triggered += $this->schedule($trigger, $now);
        }

        return ['triggered' => $triggered, 'cancelled' => $cancelled];
    }

    /** @return int the number of campaigns created */
    private function schedule(ContentTrigger $trigger, DateTimeImmutable $now): int
    {
        $audience = $trigger->getAudience();

        if (! $audience instanceof Audience) {
            return 0;
        }

        try {
            $pages = $this->pageMatcher->pages($trigger, $now);
        } catch (SegmentException $segmentException) {
            // A trigger nobody can fix from here: it stays quiet rather than
            // failing the whole tick, and says so once per run.
            $this->logger->error('Newsletter content trigger has invalid page criteria.', [
                'trigger' => $trigger->id,
                'error' => $segmentException->getMessage(),
            ]);

            return 0;
        }

        /** @var list<array{Page, Campaign}> $scheduled */
        $scheduled = [];

        foreach ($pages as $page) {
            if (null === $page->id) {
                continue;
            }

            $campaign = $this->campaignFor($trigger, $audience, $page, $now);
            $this->entityManager->persist($campaign);
            $scheduled[] = [$page, $campaign];
        }

        if ([] === $scheduled) {
            return 0;
        }

        // The markers can only be written once the campaigns have their ids, so
        // the flush is split in two rather than the log row holding a relation.
        $this->entityManager->flush();

        foreach ($scheduled as [$page, $campaign]) {
            $this->entityManager->persist(new ContentTriggerLog($trigger, (int) $page->id, (int) $campaign->id));
        }

        $this->entityManager->flush();

        return \count($scheduled);
    }

    private function campaignFor(ContentTrigger $trigger, Audience $audience, Page $page, DateTimeImmutable $now): Campaign
    {
        $publishedAt = DateTimeImmutable::createFromInterface($page->getPublishedAt() ?? $now);

        return new Campaign()
            ->setAudience($audience)
            ->setSubject($this->placeholders->render($trigger->getSubjectTemplate(), $page))
            ->setBodyMarkdown($this->placeholders->render($trigger->getBodyTemplate(), $page))
            ->setSegment($trigger->getSegment())
            ->setSlug($page->getSlug())
            ->schedule($publishedAt->modify('+'.$trigger->getDelayMinutes().' minutes'));
    }

    /**
     * Drop the campaigns whose page went away during the delay. Only campaigns
     * that have not been armed are considered: past that point the recipients
     * are frozen and some of the mails are already out.
     *
     * @return int the number of campaigns cancelled
     */
    private function cancelUnpublished(): int
    {
        $cancelled = 0;

        foreach ($this->logRepository->findForCampaigns($this->campaignRepository->findPendingIds()) as $log) {
            $page = $this->pageRepository->find($log->getPageId());

            if ($page instanceof Page && $page->isPublished()) {
                continue;
            }

            $campaign = $this->campaignRepository->find($log->getCampaignId());
            if ($campaign instanceof Campaign) {
                $this->entityManager->remove($campaign);
            }

            // The marker goes with it: should the page be published again, that
            // publication deserves the mail this one was about to get.
            $this->entityManager->remove($log);
            ++$cancelled;
        }

        if ($cancelled > 0) {
            $this->entityManager->flush();
        }

        return $cancelled;
    }
}
