<?php

namespace Pushword\Snippet\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Pushword\Core\Cache\RenderEpoch;
use Pushword\Snippet\Entity\Snippet;

/**
 * A snippet renders inside any page's content: every write invalidates the
 * rendered output of its host (all hosts when the snippet is host-less).
 */
#[AsEntityListener(event: Events::postPersist, entity: Snippet::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Snippet::class)]
#[AsEntityListener(event: Events::postRemove, entity: Snippet::class)]
final readonly class SnippetCacheInvalidationListener
{
    public function __construct(
        private RenderEpoch $renderEpoch,
    ) {
    }

    public function postPersist(Snippet $snippet): void
    {
        $this->bump($snippet);
    }

    public function postUpdate(Snippet $snippet): void
    {
        $this->bump($snippet);
    }

    public function postRemove(Snippet $snippet): void
    {
        $this->bump($snippet);
    }

    private function bump(Snippet $snippet): void
    {
        $this->renderEpoch->bump('' === $snippet->host ? null : $snippet->host);
    }
}
