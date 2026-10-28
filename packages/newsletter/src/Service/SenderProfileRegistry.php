<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Service;

use InvalidArgumentException;
use Pushword\Newsletter\Delivery\SendContext;
use Pushword\Newsletter\Entity\Campaign;

/** A site authorizes identities in configuration; editors only select them. */
final readonly class SenderProfileRegistry
{
    /** @param array<string, array{label: string, from_email: string, from_name: string, reply_to: ?string, signature_markdown: ?string}> $profiles */
    public function __construct(private array $profiles = [])
    {
        foreach ($profiles as $id => $profile) {
            if (false === filter_var($profile['from_email'], \FILTER_VALIDATE_EMAIL)
                || (null !== $profile['reply_to'] && false === filter_var($profile['reply_to'], \FILTER_VALIDATE_EMAIL))) {
                throw new InvalidArgumentException('Invalid sender profile address: '.$id);
            }
        }
    }

    /** @return array<string, string> label => id */
    public function choices(): array
    {
        $choices = [];
        foreach ($this->profiles as $id => $profile) {
            $choices[$profile['label']] = $id;
        }

        return $choices;
    }

    public function resolve(Campaign $campaign): ?SendContext
    {
        if (null === $campaign->senderProfile) {
            return null;
        }

        $profile = $this->profiles[$campaign->senderProfile] ?? throw new InvalidArgumentException('Unknown sender profile: '.$campaign->senderProfile);
        $audience = $campaign->audience ?? throw new InvalidArgumentException('Campaign has no audience.');
        $context = SendContext::fromAudience($audience);

        return new SendContext(
            $context->mainHost,
            $profile['from_email'],
            $profile['from_name'],
            $profile['reply_to'] ?? $audience->replyTo,
            $context->postalAddress,
            $context->footerMarkdown,
            $context->audienceName,
            $context->systemLinkBaseUrl,
            $profile['signature_markdown'],
        );
    }
}
