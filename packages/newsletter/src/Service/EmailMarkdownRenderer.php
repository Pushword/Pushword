<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Service;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\Autolink\EmailAutolinkParser;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;

/** Email content must not depend on the web renderer's JavaScript or media markup. */
final readonly class EmailMarkdownRenderer
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AttributesExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new TaskListExtension());
        $environment->addInlineParser(new EmailAutolinkParser());
        $environment->addEventListener(DocumentParsedEvent::class, static function (DocumentParsedEvent $event): void {
            foreach ($event->getDocument()->iterator() as $node) {
                if ($node instanceof Image) {
                    $node->data->set('attributes.width', '100%');
                    $node->data->set('attributes.style', 'display:block;width:100%;max-width:100%;height:auto;');
                    $node->data->remove('attributes.height');
                }
            }
        }, -10);

        $this->converter = new MarkdownConverter($environment);
    }

    public function transform(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}
