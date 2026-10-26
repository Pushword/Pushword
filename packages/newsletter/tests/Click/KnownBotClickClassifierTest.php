<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Click;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Newsletter\Click\ClickPayload;
use Pushword\Newsletter\Click\KnownBotClickClassifier;
use Pushword\Newsletter\Click\QualifyClick;
use Symfony\Component\HttpFoundation\Request;

final class KnownBotClickClassifierTest extends TestCase
{
    #[DataProvider('agents')]
    public function testOnlyKnownSignaturesAreFlaggedAndOtherClicksStayUnknown(string $agent, ?bool $expected): void
    {
        $event = $this->event($agent);
        new KnownBotClickClassifier(true)($event);
        self::assertSame($expected, $event->automated);
        self::assertSame(KnownBotClickClassifier::METHOD, $event->method);
    }

    /** @return iterable<string, array{string, bool|null}> */
    public static function agents(): iterable
    {
        yield 'search crawler' => ['Mozilla/5.0 (compatible; Googlebot/2.1)', true];
        yield 'preview' => ['Slackbot-LinkExpanding 1.0', true];
        yield 'security scanner' => ['Proofpoint URL Defense', true];
        yield 'ordinary browser' => ['Mozilla/5.0 Chrome/156.0.0.0 Safari/537.36', null];
        yield 'missing agent' => ['', null];
    }

    public function testDisabledClassifierAndApplicationQualificationArePreserved(): void
    {
        $disabled = $this->event('Googlebot');
        new KnownBotClickClassifier()($disabled);
        self::assertNull($disabled->automated);
        self::assertSame('unclassified', $disabled->method);

        $event = $this->event('Googlebot');
        $event->automated = false;
        $event->method = 'application-proof/v1';
        new KnownBotClickClassifier(true)($event);
        self::assertFalse($event->automated);
        self::assertSame('application-proof/v1', $event->method);
    }

    private function event(string $agent): QualifyClick
    {
        return new QualifyClick(Request::create('/newsletter/c/test', server: ['HTTP_USER_AGENT' => $agent]), new ClickPayload(1, 'https://example.org/'));
    }
}
