<?php

namespace Pushword\Core\Service\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;
use Pushword\Core\Component\EntityFilter\Filter\Date;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Service\Markdown\Extension\PushwordExtension;
use Pushword\Core\Site\SiteRegistry;
use Twig\Attribute\AsTwigFilter;
use Twig\Environment as TwigEnvironment;

class MarkdownParser
{
    private readonly MarkdownConverter $converter;

    public function __construct(
        LinkProvider $linkProvider,
        TwigEnvironment $twig,
        SiteRegistry $apps
    ) {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AttributesExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new TaskListExtension());
        $environment->addExtension(new PushwordExtension(
            $linkProvider,
            $twig,
            $apps,
            new Date($apps),
        ));

        $this->converter = new MarkdownConverter($environment);
    }

    #[AsTwigFilter('markdown', isSafe: ['html'])]
    public function transform(string $text): string
    {
        return $this->converter->convert($text)->__toString();
    }
}
