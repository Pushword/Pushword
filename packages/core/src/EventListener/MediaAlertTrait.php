<?php

declare(strict_types=1);

namespace Pushword\Core\EventListener;

use Pushword\Core\Utils\FlashBag;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

trait MediaAlertTrait
{
    /**
     * @param array<string, string> $parameters
     */
    private function alert(string $type, string $message, array $parameters = []): void
    {
        if (($flashBag = FlashBag::get($this->requestStack->getCurrentRequest())) instanceof FlashBagInterface) {
            $flashBag->add($type, $this->translator->trans($message, $parameters));
        }

        // else log TODO
    }
}
