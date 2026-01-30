<?php

namespace Pushword\Core\Extension\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\ExtensionInterface;
use Pushword\Core\Extension\Markdown\Node\ObfuscatedLink;
use Pushword\Core\Extension\Markdown\Parser\ObfuscatedLinkParser;
use Pushword\Core\Extension\Markdown\Renderer\ImageRenderer;
use Pushword\Core\Extension\Markdown\Renderer\ObfuscatedLinkRenderer;
use Pushword\Core\Service\LinkProvider;
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
        private SiteRegistry $apps
    ) {
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        // Parser pour les liens obfusqués #[text](url)
        $environment->addInlineParser(new ObfuscatedLinkParser());

        // Renderer pour les liens obfusqués
        $environment->addRenderer(
            ObfuscatedLink::class,
            new ObfuscatedLinkRenderer($this->linkProvider)
        );

        // Renderer personnalisé pour les images
        $environment->addRenderer(
            Image::class,
            new ImageRenderer($this->twig, $this->apps),
            10 // Priorité haute pour surcharger le renderer par défaut
        );
    }
}
