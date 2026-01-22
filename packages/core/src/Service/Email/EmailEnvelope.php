<?php

declare(strict_types=1);

namespace Pushword\Core\Service\Email;

/**
 * Value object representing resolved email sender/recipient configuration.
 */
final readonly class EmailEnvelope
{
    /**
     * @param string[] $to
     */
    public function __construct(
        public string $from,
        public array $to,
        public ?string $replyTo = null,
    ) {
    }

    public function isValid(): bool
    {
        if ('' === $this->from || ! filter_var($this->from, \FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if ([] === $this->to) {
            return false;
        }

        return array_all($this->to, fn ($email) => filter_var($email, \FILTER_VALIDATE_EMAIL));
    }

    public function getFirstRecipient(): string
    {
        return $this->to[0] ?? '';
    }

    /**
     * Create a new envelope with a different replyTo address.
     */
    public function withReplyTo(string $replyTo): self
    {
        return new self($this->from, $this->to, $replyTo);
    }
}
