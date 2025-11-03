<?php

namespace Pushword\Core\Service\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;
use Twig\Attribute\AsTwigFilter;

class MarkdownParser
{
    private readonly MarkdownConverter $converter;

    public function __construct(
        private MarkdownParserObfuscateLink $markdownParserObfuscateLink,
        private MarkdownParserImage $markdownParserImage
    ) {
        $config = [];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AttributesExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new TaskListExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    #[AsTwigFilter('markdown', isSafe: ['html'])]
    public function transform(string $text): string
    {
        $text = $this->markdownParserObfuscateLink->parse($text);
        $text = $this->markdownParserImage->parse($text);

        return $this->converter->convert($text)->__toString();
    }
}
