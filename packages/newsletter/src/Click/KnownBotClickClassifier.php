<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Click;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** Positive known-agent detection only: an ordinary browser never proves a human click. */
#[AsEventListener(event: QualifyClick::class, priority: -100)]
final readonly class KnownBotClickClassifier
{
    public const string METHOD = 'known-user-agent/v1';

    public function __construct(#[Autowire(param: 'pw.newsletter.bot_click_qualification')] private bool $enabled = false)
    {
    }

    public function __invoke(QualifyClick $event): void
    {
        if (! $this->enabled || null !== $event->automated || 'unclassified' !== $event->method) {
            return;
        }

        $event->method = self::METHOD;
        $agent = $event->request->headers->get('User-Agent', '');
        if (1 === preg_match('/(?:Googlebot|bingbot|DuckDuckBot|Baiduspider|YandexBot|facebookexternalhit|Slackbot-LinkExpanding|Discordbot|Twitterbot|LinkedInBot|Applebot|Proofpoint URL Defense|Mimecast URL Protection)/i', $agent)) {
            $event->automated = true;
        }
    }
}
