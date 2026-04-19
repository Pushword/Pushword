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
use Pushword\Core\Twig\MediaExtension;

final readonly class PushwordExtension implements ExtensionInterface
{
    public function __construct(
        private LinkProvider $linkProvider,
        private MediaExtension $mediaExtension,
    ) {
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser(new ObfuscatedLinkParser());

        $environment->addRenderer(
            ObfuscatedLink::class,
            new ObfuscatedLinkRenderer($this->linkProvider)
        );

        $environment->addRenderer(
            Image::class,
            new ImageRenderer($this->mediaExtension),
            10
        );
    }
}
