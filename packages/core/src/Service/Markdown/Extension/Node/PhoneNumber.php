<?php

namespace Pushword\Core\Service\Markdown\Extension\Node;

use League\CommonMark\Node\Inline\AbstractInline;

/**
 * Représente un numéro de téléphone.
 */
class PhoneNumber extends AbstractInline
{
    public function __construct(
        private readonly string $number
    ) {
        parent::__construct();
    }

    public function getNumber(): string
    {
        return $this->number;
    }
}
