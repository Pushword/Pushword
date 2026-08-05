<?php

namespace Pushword\Newsletter\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Ties a delivery report to a message this install actually sent.
 *
 * The bounce mailbox is a real address, published in the `Return-Path` of every
 * newsletter, and by design it accepts mail from any server on the internet.
 * Nothing about a `multipart/report` arriving there says a mail server wrote it:
 * a hand-written message naming `Final-Recipient: someone@example.tld` looks
 * exactly like the genuine article, and taking an address off the list is
 * terminal — {@see ContactManager::resubscribe()} refuses to revive a bounce.
 * Read without proof, the mailbox is a way for anyone to unsubscribe anyone.
 *
 * The proof is the `Message-ID`. Instead of keeping a ledger of the ones we
 * issued, each is made to carry its own evidence:
 *
 *     nl.<nonce>.<hmac(nonce, recipient)>@<sending domain>
 *
 * A report is honoured for an address only when it gives back a `Message-ID`
 * whose signature recomputes for that same address. Producing one needs the
 * secret, or a copy of a mail we really sent there — which is what a mail
 * server returning our own message has, and what a forger does not.
 *
 * The nonce is not decoration: a `Message-ID` fixed per recipient would repeat
 * across every campaign, and inboxes that deduplicate on it drop the repeat.
 */
final readonly class BounceSignature
{
    /** Long enough that guessing one is not a strategy, short enough to read in a header. */
    private const int NONCE_BYTES = 8;

    private const int SIGNATURE_LENGTH = 32;

    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        private string $secret,
    ) {
    }

    /**
     * The `Message-ID` to stamp on a mail, angle brackets excluded.
     *
     * @param string $sender the address the mail is from, whose domain the id belongs to
     */
    public function messageId(string $recipient, string $sender): string
    {
        $nonce = bin2hex(random_bytes(self::NONCE_BYTES));
        $senderParts = explode('@', $sender);

        return 'nl.'.$nonce.'.'.$this->sign($nonce, $recipient).'@'.end($senderParts);
    }

    /**
     * Whether any of the ids a report gave back was issued for this address.
     *
     * A report names one failed recipient per group but returns a single copy of
     * the message, so every id found in it is a candidate for every failure —
     * which costs nothing, the signature being what decides.
     *
     * @param list<string> $messageIds
     */
    public function proves(string $email, array $messageIds): bool
    {
        return array_any($messageIds, fn (string $messageId): bool => $this->matches($email, $messageId));
    }

    private function matches(string $email, string $messageId): bool
    {
        if (1 !== preg_match('/^nl\.([0-9a-f]+)\.([0-9a-f]{'.self::SIGNATURE_LENGTH.'})@/', $messageId, $matches)) {
            return false;
        }

        return hash_equals($this->sign($matches[1], $email), $matches[2]);
    }

    /**
     * The address is folded to lower case on both sides: it is written here from
     * the contact and read back from a `Final-Recipient` the remote server
     * echoed in whatever case it pleased.
     */
    private function sign(string $nonce, string $email): string
    {
        return mb_substr(
            hash_hmac('sha256', $nonce.':'.mb_strtolower(trim($email)), $this->secret),
            0,
            self::SIGNATURE_LENGTH,
        );
    }
}
