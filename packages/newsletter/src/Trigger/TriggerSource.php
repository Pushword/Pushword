<?php

namespace Pushword\Newsletter\Trigger;

use DateTimeImmutable;
use Pushword\Newsletter\Criteria\AbstractCriteria;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Segment\SegmentException;

/**
 * What an automation watches.
 *
 * Two ship with the bundle — contacts and pages — and a site adds its own by
 * implementing this and tagging the service `pushword.newsletter.trigger_source`.
 * A source that watches orders, bookings or customers is then a first-class
 * trigger: it appears in the admin's source list, its vocabulary validates in the
 * same textarea, and the steps, the delays, the segment and the reporting are the
 * ones every other automation already uses.
 *
 * A source answers three questions and holds no state. Remembering what has been
 * handled is the automation's business — {@see \Pushword\Newsletter\Entity\TriggerLog}
 * — so a source that returns the same subject twice is corrected rather than
 * believed, and one written without a `LIMIT` is still safe to call.
 */
interface TriggerSource
{
    /**
     * Stored on the automation, chosen in the admin, sent by the API. Stable:
     * renaming it orphans every automation that picked it.
     */
    public function name(): string;

    /**
     * The vocabulary an automation's `triggerWhen` is written in — the class
     * that says which fields and operators exist, and refuses the rest.
     *
     * @return class-string<AbstractCriteria>
     */
    public function criteria(): string;

    /**
     * What newly matches: subjects the automation has not handled, that occurred
     * after its `activeFrom` and no later than `$now`.
     *
     * Ordered oldest first — a tick that runs into its limit must leave the
     * newest behind, never the oldest, or a backlog never drains.
     *
     * @return list<TriggerOccurrence>
     *
     * @throws SegmentException when the rule cannot be read
     */
    public function occurrences(Automation $automation, DateTimeImmutable $now, ?int $limit = null): array;

    /**
     * How many are waiting, for the admin's preview and the API's report. Counts
     * without building, so a rule matching a whole back catalogue is cheap to
     * look at before it is switched on.
     *
     * @throws SegmentException when the rule cannot be read
     */
    public function count(Automation $automation, DateTimeImmutable $now): int;

    /**
     * Does this subject still deserve the mail waiting for it? Asked during the
     * delay, once per tick, about subjects whose campaigns have not been armed:
     * a page unpublished the evening it was announced answers no, and the mail
     * is dropped before anyone receives it.
     *
     * A subject that has since vanished answers no too — being unable to find it
     * is not a reason to mail about it.
     */
    public function stillMatches(int $subjectId): bool;
}
