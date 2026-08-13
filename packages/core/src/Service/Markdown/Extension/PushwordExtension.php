<?php

namespace Pushword\Core\Service\Markdown\Extension;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\ExtensionInterface;
use Pushword\Core\Component\EntityFilter\Filter\Date;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Service\Markdown\Extension\Node\ObfuscatedEmail;
use Pushword\Core\Service\Markdown\Extension\Node\ObfuscatedLink;
use Pushword\Core\Service\Markdown\Extension\Node\PhoneNumber;
use Pushword\Core\Service\Markdown\Extension\Parser\DateShortcodeParser;
use Pushword\Core\Service\Markdown\Extension\Parser\EmailAutolinkParser;
use Pushword\Core\Service\Markdown\Extension\Parser\PhoneAutolinkParser;
use Pushword\Core\Service\Markdown\Extension\Processor\ColspanProcessor;
use Pushword\Core\Service\Markdown\Extension\Processor\EmptyTableHeadProcessor;
use Pushword\Core\Service\Markdown\Extension\Processor\ObfuscatedLinkProcessor;
use Pushword\Core\Service\Markdown\Extension\Renderer\FencedCodeRenderer;
use Pushword\Core\Service\Markdown\Extension\Renderer\ImageRenderer;
use Pushword\Core\Service\Markdown\Extension\Renderer\ObfuscatedEmailRenderer;
use Pushword\Core\Service\Markdown\Extension\Renderer\ObfuscatedLinkRenderer;
use Pushword\Core\Service\Markdown\Extension\Renderer\PhoneNumberRenderer;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;

final readonly class PushwordExtension implements ExtensionInterface
{
    public function __construct(
        private LinkProvider $linkProvider,
        private MediaExtension $mediaExtension,
        private SiteRegistry $apps,
        private Date $dateFilter,
    ) {
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser(new EmailAutolinkParser(), 100);

        $environment->addInlineParser(new PhoneAutolinkParser(), 100);

        $environment->addInlineParser(new DateShortcodeParser($this->dateFilter, $this->apps->get()->locale), 150);

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
            new ImageRenderer($this->mediaExtension, $this->apps),
            10
        );

        // Opt-in per site: when `fenced_code_pre_class` is set in the app config,
        // wrap fenced code blocks in <pre class="..."> so a highlighter (microlight,
        // hljs, prism…) can pick them up. Registered unconditionally because the
        // renderer reads the class per render — this runs once per process, and a
        // site declaring none gets League's output byte for byte anyway.
        //
        // The priority is what makes it run at all: CommonMarkCoreExtension registers
        // its own FencedCode renderer, is added to the environment first, and always
        // returns output — so at equal priority ours was never reached. Same reason
        // the image renderer above takes 10.
        $environment->addRenderer(FencedCode::class, new FencedCodeRenderer($this->apps), 10);

        $environment->addEventListener(DocumentParsedEvent::class, new ColspanProcessor());

        $environment->addEventListener(DocumentParsedEvent::class, new EmptyTableHeadProcessor());

        $environment->addEventListener(DocumentParsedEvent::class, new ObfuscatedLinkProcessor());
    }
}
