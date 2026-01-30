<?php

namespace Pushword\Core\Service\Markdown\Extension;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\ExtensionInterface;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Service\Markdown\Extension\Node\ObfuscatedEmail;
use Pushword\Core\Service\Markdown\Extension\Node\ObfuscatedLink;
use Pushword\Core\Service\Markdown\Extension\Node\PhoneNumber;
use Pushword\Core\Service\Markdown\Extension\Parser\DateShortcodeParser;
use Pushword\Core\Service\Markdown\Extension\Parser\EmailAutolinkParser;
use Pushword\Core\Service\Markdown\Extension\Parser\ObfuscatedLinkParser;
use Pushword\Core\Service\Markdown\Extension\Parser\PhoneAutolinkParser;
use Pushword\Core\Service\Markdown\Extension\Renderer\ImageRenderer;
use Pushword\Core\Service\Markdown\Extension\Renderer\ObfuscatedEmailRenderer;
use Pushword\Core\Service\Markdown\Extension\Renderer\ObfuscatedLinkRenderer;
use Pushword\Core\Service\Markdown\Extension\Renderer\PhoneNumberRenderer;
use Pushword\Core\Site\SiteRegistry;
use Twig\Environment;

/**
 * Extension league/commonmark pour Pushword.
 * Gère les liens obfusqués et les images personnalisées.
 */
final readonly class PushwordExtension implements ExtensionInterface
{
    public function __construct(
        private LinkProvider $linkProvider,
        private Environment $twig,
        private SiteRegistry $apps,
    ) {
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser(new ObfuscatedLinkParser(), 200);

        $environment->addInlineParser(new EmailAutolinkParser(), 100);

        $environment->addInlineParser(new PhoneAutolinkParser(), 100);

        $environment->addInlineParser(new DateShortcodeParser($this->apps), 150);

        $environment->addRenderer(
            ObfuscatedLink::class,
            new ObfuscatedLinkRenderer($this->linkProvider)
        );

        $environment->addRenderer(
            ObfuscatedEmail::class,
            new ObfuscatedEmailRenderer($this->linkProvider)
        );

        $environment->addRenderer(
            PhoneNumber::class,
            new PhoneNumberRenderer($this->linkProvider)
        );

        $environment->addRenderer(
            Image::class,
            new ImageRenderer($this->twig, $this->apps),
            10 // Priorité haute pour surcharger le renderer par défaut
        );
    }
}
