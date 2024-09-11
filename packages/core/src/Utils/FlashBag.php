<?php

namespace Pushword\Core\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

class FlashBag
{
    /**
     * @psalm-suppress all
     */
    public static function get(?Request $currentRequest = null): ?FlashBagInterface
    {
        return null !== $currentRequest && method_exists($currentRequest->getSession(), 'getFlashBag')
            ? $currentRequest->getSession()->getFlashBag()
            : null;
    }
}
