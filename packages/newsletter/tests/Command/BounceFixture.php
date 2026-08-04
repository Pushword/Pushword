<?php

namespace Pushword\Newsletter\Tests\Command;

/**
 * A delivery report shaped after what a Postfix relay actually returns, folded
 * `Content-Type` header included: a boundary that ends up on its own line is the
 * first thing a naive parser loses.
 */
final class BounceFixture
{
    public static function bounce(string $email, string $status): string
    {
        return str_replace("\n", "\r\n", <<<MAIL
            From: MAILER-DAEMON@relay.example.tld (Mail Delivery System)
            Subject: Undelivered Mail Returned to Sender
            To: bounce@example.tld
            MIME-Version: 1.0
            Content-Type: multipart/report; report-type=delivery-status;
            \tboundary="BOUNDARY"

            --BOUNDARY
            Content-Type: text/plain; charset=us-ascii

            Your message could not be delivered.

            --BOUNDARY
            Content-Type: message/delivery-status

            Reporting-MTA: dns; relay.example.tld

            Final-Recipient: rfc822; {$email}
            Action: failed
            Status: {$status}
            Diagnostic-Code: smtp; 550 no such user

            --BOUNDARY--
            MAIL);
    }
}
