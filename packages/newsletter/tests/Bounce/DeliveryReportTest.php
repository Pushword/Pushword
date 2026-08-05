<?php

namespace Pushword\Newsletter\Tests\Bounce;

use PHPUnit\Framework\TestCase;
use Pushword\Newsletter\Bounce\DeliveryReport;
use Pushword\Newsletter\Bounce\FailedRecipient;

/**
 * The fixtures are shaped after what a Postfix relay actually returns, folded
 * `Content-Type` header included: a boundary that ends up on its own line is
 * the first thing a naive parser loses.
 */
final class DeliveryReportTest extends TestCase
{
    public function testAPermanentFailureNamesTheAddress(): void
    {
        $report = DeliveryReport::fromRaw($this->bounce('5.1.1', 'failed'));

        self::assertInstanceOf(DeliveryReport::class, $report);
        self::assertCount(1, $report->failures);
        self::assertSame('no-such-user@example.tld', $report->failures[0]->email);
        self::assertSame('5.1.1', $report->failures[0]->status);
        self::assertStringContainsString('550', $report->failures[0]->diagnostic);
    }

    /** A full mailbox is not a reason to lose a reader. */
    public function testATemporaryFailureIsReadButNotReported(): void
    {
        $report = DeliveryReport::fromRaw($this->bounce('4.2.2', 'failed'));

        self::assertInstanceOf(DeliveryReport::class, $report);
        self::assertSame([], $report->failures);
    }

    public function testADelayNoticeIsNotAFailure(): void
    {
        $report = DeliveryReport::fromRaw($this->bounce('4.4.1', 'delayed'));

        self::assertInstanceOf(DeliveryReport::class, $report);
        self::assertSame([], $report->failures);
    }

    /** Anything else the mailbox receives has to be told apart from a report. */
    public function testAnOrdinaryMessageIsNotADeliveryReport(): void
    {
        $message = "From: someone@example.tld\nTo: bounce@example.tld\nSubject: Hello\nContent-Type: text/plain\n\nFinal-Recipient: rfc822; victim@example.tld\nAction: failed\nStatus: 5.1.1\n";

        self::assertNull(DeliveryReport::fromRaw($message));
    }

    public function testEveryFailedRecipientOfOneReportIsNamed(): void
    {
        $raw = str_replace(
            '--BOUNDARY--',
            "\nFinal-Recipient: rfc822; <second@example.tld>\nAction: failed\nStatus: 5.2.1\n\n--BOUNDARY--",
            $this->bounce('5.1.1', 'failed'),
        );

        $report = DeliveryReport::fromRaw($raw);

        self::assertInstanceOf(DeliveryReport::class, $report);
        self::assertSame(
            ['no-such-user@example.tld', 'second@example.tld'],
            array_map(static fn (FailedRecipient $failure): string => $failure->email, $report->failures),
        );
    }

    /** Mail arrives with CRLF line endings, whatever a fixture is written with. */
    public function testCrlfIsRead(): void
    {
        $report = DeliveryReport::fromRaw(str_replace("\n", "\r\n", $this->bounce('5.1.1', 'failed')));

        self::assertInstanceOf(DeliveryReport::class, $report);
        self::assertCount(1, $report->failures);
    }

    /**
     * Which message failed is the only thing that ties a report to this install
     * — see {@see \Pushword\Newsletter\Service\BounceSignature}. Postfix returns
     * the headers alone.
     */
    public function testTheReturnedHeadersNameTheMessageThatFailed(): void
    {
        $report = DeliveryReport::fromRaw($this->bounce('5.1.1', 'failed', 'nl.deadbeef.cafe@example.tld'));

        self::assertInstanceOf(DeliveryReport::class, $report);
        self::assertSame(['nl.deadbeef.cafe@example.tld'], $report->messageIds);
    }

    /** Others return the whole message. */
    public function testAWholeReturnedMessageNamesItToo(): void
    {
        $raw = str_replace('text/rfc822-headers', 'message/rfc822', $this->bounce('5.1.1', 'failed', 'nl.deadbeef.cafe@example.tld'));

        $report = DeliveryReport::fromRaw($raw);

        self::assertInstanceOf(DeliveryReport::class, $report);
        self::assertSame(['nl.deadbeef.cafe@example.tld'], $report->messageIds);
    }

    /**
     * A `Message-ID:` written into the body of the returned message is not the
     * header — a forger who cannot sign one would otherwise only have to type it
     * one blank line lower.
     */
    public function testAMessageIdInTheReturnedBodyIsNotTheHeader(): void
    {
        $raw = str_replace(
            "Subject: This week\n",
            "Subject: This week\n\nMessage-ID: <nl.forged.beef@example.tld>\n",
            str_replace('text/rfc822-headers', 'message/rfc822', $this->bounce('5.1.1', 'failed', 'nl.deadbeef.cafe@example.tld')),
        );

        $report = DeliveryReport::fromRaw($raw);

        self::assertInstanceOf(DeliveryReport::class, $report);
        self::assertSame(['nl.deadbeef.cafe@example.tld'], $report->messageIds);
    }

    /** RFC 3464 only recommends returning the message, so a report may name none. */
    public function testAReportReturningNothingNamesNoMessage(): void
    {
        $report = DeliveryReport::fromRaw($this->bounce('5.1.1', 'failed'));

        self::assertInstanceOf(DeliveryReport::class, $report);
        self::assertSame([], $report->messageIds);
    }

    /** A copy can come back stripped of the one header that identifies it. */
    public function testAReturnedCopyWithoutAMessageIdNamesNoMessage(): void
    {
        $raw = str_replace(
            "Message-ID: <nl.deadbeef.cafe@example.tld>\n",
            '',
            $this->bounce('5.1.1', 'failed', 'nl.deadbeef.cafe@example.tld'),
        );

        $report = DeliveryReport::fromRaw($raw);

        self::assertInstanceOf(DeliveryReport::class, $report);
        self::assertCount(1, $report->failures, 'the failure is still read');
        self::assertSame([], $report->messageIds);
    }

    private function bounce(string $status, string $action, string $messageId = ''): string
    {
        $returned = '' === $messageId ? '' : <<<MAIL

            --BOUNDARY
            Content-Description: Undelivered Message Headers
            Content-Type: text/rfc822-headers

            Return-Path: <bounce@example.tld>
            Message-ID: <{$messageId}>
            From: News <news@example.tld>
            To: no-such-user@example.tld
            Subject: This week
            MAIL."\n";

        return str_replace('--BOUNDARY--', $returned."\n--BOUNDARY--", $this->report($status, $action));
    }

    private function report(string $status, string $action): string
    {
        return <<<MAIL
            From: MAILER-DAEMON@relay.example.tld (Mail Delivery System)
            Subject: Undelivered Mail Returned to Sender
            To: bounce@example.tld
            Auto-Submitted: auto-replied
            MIME-Version: 1.0
            Content-Type: multipart/report; report-type=delivery-status;
            \tboundary="BOUNDARY"

            --BOUNDARY
            Content-Description: Notification
            Content-Type: text/plain; charset=us-ascii

            I'm sorry to have to inform you that your message could not
            be delivered to one or more recipients.

            --BOUNDARY
            Content-Description: Delivery report
            Content-Type: message/delivery-status

            Reporting-MTA: dns; relay.example.tld
            X-Postfix-Sender: rfc822; bounce@example.tld
            Arrival-Date: Mon, 3 Aug 2026 19:45:16 +0000 (UTC)

            Final-Recipient: rfc822; no-such-user@example.tld
            Original-Recipient: rfc822;no-such-user@example.tld
            Action: {$action}
            Status: {$status}
            Remote-MTA: dns; mx.example.tld
            Diagnostic-Code: smtp; 550-5.1.1 The email account that you tried to reach does not exist

            --BOUNDARY--
            MAIL;
    }
}
