<?php

namespace Pushword\Core\Utils;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

class FlashBag
{
    public static function get(?Request $currentRequest = null): ?FlashBagInterface
    {
        // @phpstan-ignore-next-line
        return null !== $currentRequest && method_exists($currentRequest->getSession(), 'getFlashBag')
            ? $currentRequest->getSession()->getFlashBag()
            : null;
    }
}
