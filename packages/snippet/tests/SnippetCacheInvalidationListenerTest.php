<?php

namespace Pushword\Snippet\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Cache\RenderEpoch;
use Pushword\Snippet\Entity\Snippet;
use Pushword\Snippet\EventListener\SnippetCacheInvalidationListener;

final class SnippetCacheInvalidationListenerTest extends TestCase
{
    /** @var RenderEpoch&MockObject */
    private MockObject $renderEpoch;

    private SnippetCacheInvalidationListener $listener;

    protected function setUp(): void
    {
        $this->renderEpoch = $this->createMock(RenderEpoch::class);
        $this->listener = new SnippetCacheInvalidationListener($this->renderEpoch);
    }

    public function testEveryWriteBumpsTheSnippetHost(): void
    {
        $snippet = new Snippet();
        $snippet->host = 'example.tld';

        $this->renderEpoch->expects($this->exactly(3))->method('bump')->with('example.tld');

        $this->listener->postPersist($snippet);
        $this->listener->postUpdate($snippet);
        $this->listener->postRemove($snippet);
    }

    public function testHostLessSnippetBumpsEveryHost(): void
    {
        // A host-less snippet is a fallback rendered by any app.
        $snippet = new Snippet();

        $this->renderEpoch->expects($this->once())->method('bump')->with(null);

        $this->listener->postUpdate($snippet);
    }
}
