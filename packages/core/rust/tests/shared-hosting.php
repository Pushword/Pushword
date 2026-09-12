<?php

declare(strict_types=1);

use Pushword\Core\Component\EntityFilter\ValueObject\SplitContent;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\ContentSplitter;

require __DIR__.'/../../../../vendor/autoload.php';

if (\function_exists('proc_open')) {
    throw new RuntimeException('Run with proc_open disabled');
}

$page = new Page();
$page->setCustomProperty('toc', true);
$html = '<p>Lede.</p><!--break--><p>Intro.</p><h2>Title</h2><p>Text.</p>';
$expected = new SplitContent($html, $page);
foreach ([new ContentSplitter(), new ContentSplitter(__DIR__.'/../target/release/pushword-content-analyzer')] as $splitter) {
    $actual = $splitter->split($html, $page);
    if ((string) $expected !== (string) $actual || $expected->getToc() !== $actual->getToc()
        || $expected->getParagraphs(true) !== $actual->getParagraphs(true)) {
        throw new RuntimeException('PHP fallback changed content');
    }
}

echo "PHP-only split verified\n";
