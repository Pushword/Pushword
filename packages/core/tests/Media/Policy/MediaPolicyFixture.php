<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Media\Policy;

use Pushword\Core\Event\MediaPreRemoveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Stands in for an app's own media rules, plugged in the way an app would: the
 * constraint through a validation mapping file (media_policy.yaml, loaded by the test
 * env only), the removal veto as this event subscriber (services_test.php).
 *
 * Both key off markers no real media carries, so the rest of the suite never meets them.
 */
final class MediaPolicyFixture implements EventSubscriberInterface
{
    /** A target file name, alt or credit carrying it fails validation (media_policy.yaml). */
    public const string REJECTED = 'policy-rejected';

    public const string MESSAGE = 'Refused by the test media policy.';

    /** A media whose file name carries it may not be removed. */
    public const string LOCKED = 'policy-locked';

    /** Markup on purpose: the admin renders flashes raw, so a reason must reach it escaped. */
    public const string LOCK_REASON = 'Locked by the <b>test</b> media policy.';

    public static function getSubscribedEvents(): array
    {
        return [MediaPreRemoveEvent::NAME => 'onPreRemove'];
    }

    public function onPreRemove(MediaPreRemoveEvent $event): void
    {
        if (str_contains($event->media->getFileName(), self::LOCKED)) {
            $event->refuse(self::LOCK_REASON);
        }
    }
}
