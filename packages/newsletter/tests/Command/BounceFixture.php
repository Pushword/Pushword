<?php

namespace Pushword\Newsletter\Tests\Command;

/**
 * A delivery report shaped after what a Postfix relay actually returns, folded
 * `Content-Type` header included: a boundary that ends up on its own line is the
 * first thing a naive parser loses.
 *
 * The returned copy of the message that failed is what makes a report anything
 * more than an assertion. Leave `$messageId` empty and what comes out is what
 * anybody on the internet can post to the bounce mailbox by hand.
 */
final class BounceFixture
{
    public static function bounce(string $email, string $status, string $messageId = ''): string
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

            MAIL.self::returnedHeaders($email, $messageId).'--BOUNDARY--');
    }

    /**
     * What a relay sends back of the message it gave up on — the headers alone,
     * which is the smaller of the two forms RFC 3464 allows and the one that
     * leaves the least for a parser to find.
     *
     * Either way this closes the part above it, hence the leading newline: a
     * MIME boundary is preceded by a blank line.
     */
    private static function returnedHeaders(string $email, string $messageId): string
    {
        if ('' === $messageId) {
            return "\n";
        }

        return <<<MAIL

            --BOUNDARY
            Content-Description: Undelivered Message Headers
            Content-Type: text/rfc822-headers

            Return-Path: <bounce@example.tld>
            Message-ID: <{$messageId}>
            From: News <news@example.tld>
            To: {$email}
            Subject: This week
            MAIL."\n\n";
    }
}
