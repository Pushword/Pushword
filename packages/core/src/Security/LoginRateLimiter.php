<?php

declare(strict_types=1);

namespace Pushword\Core\Security;

use Override;
use Pushword\Core\Utils\ClientNetwork;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RateLimiter\AbstractRequestRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Symfony's DefaultLoginRateLimiter keyed by ClientNetwork instead of the raw address,
 * so an IPv6 client rotating through its /64 does not get a fresh budget per address.
 */
final class LoginRateLimiter extends AbstractRequestRateLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.login_throttling_network')]
        private readonly RateLimiterFactoryInterface $networkFactory,
        #[Autowire(service: 'limiter.login_throttling_account')]
        private readonly RateLimiterFactoryInterface $accountFactory,
        #[Autowire(param: 'kernel.secret')]
        #[SensitiveParameter]
        private readonly string $secret,
    ) {
    }

    /** The network limit bounds how many accounts one client may try: a successful login keeps it. */
    #[Override]
    public function reset(Request $request): void
    {
        $this->accountLimiter($request)->reset();
    }

    /**
     * @return LimiterInterface[]
     */
    #[Override]
    protected function getLimiters(Request $request): array
    {
        return [
            $this->networkFactory->create($this->hash(ClientNetwork::of($request->getClientIp()))),
            $this->accountLimiter($request),
        ];
    }

    private function accountLimiter(Request $request): LimiterInterface
    {
        $username = mb_strtolower($request->attributes->getString(SecurityRequestAttributes::LAST_USERNAME));

        return $this->accountFactory->create($this->hash($username.'-'.ClientNetwork::of($request->getClientIp())));
    }

    private function hash(string $data): string
    {
        return strtr(substr(base64_encode(hash_hmac('sha256', $data, $this->secret, true)), 0, 8), '/+', '._');
    }
}
