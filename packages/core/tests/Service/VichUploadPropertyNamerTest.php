<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Service;

use LogicException;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Media;
use Pushword\Core\Service\VichUploadPropertyNamer;
use Vich\UploaderBundle\Mapping\PropertyMappingInterface;

final class VichUploadPropertyNamerTest extends TestCase
{
    public function testNameIsTheMediaFileName(): void
    {
        $media = new Media()->setFileName('1-2.jpg');

        self::assertSame('1-2.jpg', new VichUploadPropertyNamer()->name($media, self::createStub(PropertyMappingInterface::class)));
    }

    public function testNameRefusesWhatIsNotAMedia(): void
    {
        $this->expectException(LogicException::class);

        new VichUploadPropertyNamer()->name([], self::createStub(PropertyMappingInterface::class));
    }
}
