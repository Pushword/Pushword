<?php

namespace Pushword\Newsletter\Trigger;

use DateTimeImmutable;
use Pushword\Newsletter\Entity\Contact;

/**
 * Something happened that an automation was waiting for: a page was published, a
 * contact came to match, a customer placed their first order.
 *
 * What a source produces, and the only thing the rest of the newsletter knows
 * about the world a source watches. Everything downstream — the delay, the
 * steps, the templates — reads these five values and never the subject itself,
 * which is what lets a bundle add a source without adding a column.
 *
 * {@see self::$contact} is what picks the delivery. Set, the occurrence concerns
 * one person and the steps become a drip paced at them; null, it concerns the
 * site and the steps become campaigns broadcast to whoever the automation's
 * `recipientWhen` selects. Those are the two shapes a mail sequence has, and a
 * source says which one it means per occurrence rather than once for all.
 */
final readonly class TriggerOccurrence
{
    /**
     * @param int                   $subjectId    identifies the subject within its source; an
     *                                            automation handles each of them once, and this is
     *                                            what it remembers having handled
     * @param DateTimeImmutable     $occurredAt   when the clock starts — a publication date, not the
     *                                            moment the tick noticed it, so a delayed tick still
     *                                            mails on time
     * @param array<string, string> $placeholders what the steps' subject and body may quote, keyed as
     *                                            they are written: `page.h1`, `customer.firstName`
     * @param Contact|null          $contact      the person this is about, when it is about one
     * @param string|null           $slug         analytics name for the campaigns it produces; the
     *                                            subject's own slug reads better in a report than a
     *                                            step's subject line
     */
    public function __construct(
        public int $subjectId,
        public DateTimeImmutable $occurredAt,
        public array $placeholders = [],
        public ?Contact $contact = null,
        public ?string $slug = null,
    ) {
    }
}
