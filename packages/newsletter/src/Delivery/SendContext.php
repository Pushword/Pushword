<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Delivery;

use Pushword\Newsletter\Entity\Audience;

/** Presentation only: the contact and audience still own consent and tracking. */
final readonly class SendContext
{
    public function __construct(
        public string $mainHost,
        public string $fromEmail,
        public string $fromName,
        public ?string $replyTo = null,
        public ?string $postalAddress = null,
        public string $footerMarkdown = '',
        public ?string $audienceName = null,
        public ?string $systemLinkBaseUrl = null,
    ) {
    }

    public static function fromAudience(Audience $audience): self
    {
        return new self(
            $audience->mainHost,
            $audience->fromEmail,
            $audience->fromName,
            $audience->replyTo,
            $audience->postalAddress,
            audienceName: $audience->name
        );
    }

    /** @return array{mainHost: string, fromEmail: string, fromName: string, replyTo: ?string, postalAddress: ?string, footerMarkdown: string, audienceName: ?string, systemLinkBaseUrl: ?string} */
    public function toArray(): array
    {
        return [
            'mainHost' => $this->mainHost,
            'fromEmail' => $this->fromEmail,
            'fromName' => $this->fromName,
            'replyTo' => $this->replyTo,
            'postalAddress' => $this->postalAddress,
            'footerMarkdown' => $this->footerMarkdown,
            'audienceName' => $this->audienceName,
            'systemLinkBaseUrl' => $this->systemLinkBaseUrl,
        ];
    }

    /** @param array{mainHost: string, fromEmail: string, fromName: string, replyTo: ?string, postalAddress: ?string, footerMarkdown: string, audienceName: ?string, systemLinkBaseUrl?: ?string} $data */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }
}
