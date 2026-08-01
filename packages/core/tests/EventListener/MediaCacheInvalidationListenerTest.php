<?php

namespace Pushword\Core\Tests\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Cache\RenderEpoch;
use Pushword\Core\EventListener\MediaCacheInvalidationListener;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class MediaCacheInvalidationListenerTest extends TestCase
{
    /** @var RenderEpoch&MockObject */
    private MockObject $renderEpoch;

    private MediaCacheInvalidationListener $listener;

    protected function setUp(): void
    {
        $mediaRepository = self::createStub(MediaRepository::class);
        $em = self::createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($mediaRepository);

        $this->renderEpoch = $this->createMock(RenderEpoch::class);
        $this->listener = new MediaCacheInvalidationListener(new ArrayAdapter(), $em, $this->renderEpoch);
    }

    public function testPersistDoesNotBumpTheEpoch(): void
    {
        // A media that did not exist cannot appear in already-generated HTML;
        // bumping here would sweep every host on every upload.
        $this->renderEpoch->expects($this->never())->method('bump');

        $this->listener->postPersist();
    }

    public function testUpdateAndRemoveBumpEveryHost(): void
    {
        $this->renderEpoch->expects($this->exactly(2))->method('bump')->with(null);

        $this->listener->postUpdate();
        $this->listener->postRemove();
    }
}
