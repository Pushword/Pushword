<?php

namespace Pushword\Newsletter\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Proof that a submission came through the form endpoint.
 *
 * The subscribe endpoint is public and answers cross-origin by design, so the
 * only thing worth asking of a post is that whoever sent it first fetched a
 * form — which is what stops a script spraying the endpoint without ever
 * reading a page. A session token cannot ask it: the form is fetched by a
 * statically generated page from another domain, and the cookie carrying that
 * session is a third-party cookie, which Safari drops outright and other
 * browsers partition. The token would be issued and then never come back.
 *
 * So the token carries its own evidence instead of pointing at a session:
 *
 *     <expiry>.<hmac(expiry)>
 *
 * Nothing is stored, nothing is read back, and the check works the same whether
 * the form was fetched from the live host's own pages or from a static build on
 * another domain. Producing one needs the secret.
 *
 * The expiry is inside the signed material, so it cannot be pushed further out
 * without the secret either. It exists to bound how long a token scraped once
 * stays worth replaying — not to protect a session, there is none.
 */
final readonly class SubscribeToken
{
    /**
     * Long enough that a form sitting in a tab someone left open still submits,
     * short enough that a token lifted from one page is not a lasting key.
     */
    private const int LIFETIME = 86400;

    private const int SIGNATURE_LENGTH = 32;

    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        private string $secret,
    ) {
    }

    /** The token to put in the form the endpoint is about to serve. */
    public function issue(): string
    {
        $expiry = (string) (time() + self::LIFETIME);

        return $expiry.'.'.$this->sign($expiry);
    }

    /**
     * The signature is checked before the expiry it announces is believed: an
     * unsigned token says nothing, including about its own lifetime.
     */
    public function isValid(string $token): bool
    {
        if (1 !== preg_match('/^(\d{1,10})\.([0-9a-f]{'.self::SIGNATURE_LENGTH.'})$/', $token, $matches)) {
            return false;
        }

        if (! hash_equals($this->sign($matches[1]), $matches[2])) {
            return false;
        }

        return (int) $matches[1] >= time();
    }

    private function sign(string $expiry): string
    {
        return mb_substr(hash_hmac('sha256', $expiry, $this->secret), 0, self::SIGNATURE_LENGTH);
    }
}
