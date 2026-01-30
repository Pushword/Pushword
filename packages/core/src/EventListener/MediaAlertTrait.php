<?php

namespace Pushword\Core\EventListener;

use Pushword\Core\Utils\FlashBag;

trait MediaAlertTrait
{
    /**
     * @param array<string, string> $parameters
     */
    private function alert(string $type, string $message, array $parameters = []): void
    {
        if (null !== ($flashBag = FlashBag::get($this->requestStack->getCurrentRequest()))) {
            $flashBag->add($type, $this->translator->trans($message, $parameters));
        }

        // else log TODO
    }
}
