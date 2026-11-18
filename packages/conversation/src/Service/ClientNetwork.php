<?php

declare(strict_types=1);

namespace Pushword\Conversation\Service;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Who the conversation limiters count: an IPv4 address, or the /64 network of an IPv6
 * one. An IPv6 visitor is usually given a whole /64, so counting each address apart
 * would let it rotate past any limit.
 */
final class ClientNetwork
{
    private const string IPV4_MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    public static function of(?string $ip): string
    {
        if (null === $ip) {
            return 'unknown';
        }

        if (false === filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
            return $ip;
        }

        $packed = (string) inet_pton($ip);
        if (str_starts_with($packed, self::IPV4_MAPPED_PREFIX)) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return IpUtils::anonymize($ip, 0, 8);
    }
}
