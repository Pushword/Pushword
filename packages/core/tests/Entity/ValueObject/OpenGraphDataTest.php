<?php

namespace Pushword\Core\Tests\Entity\ValueObject;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\ValueObject\OpenGraphData;

class OpenGraphDataTest extends TestCase
{
    public function testEmptyByDefault(): void
    {
        $data = new OpenGraphData();
        self::assertTrue($data->isEmpty());
        self::assertNull($data->title);
        self::assertNull($data->description);
        self::assertNull($data->image);
    }

    public function testNotEmptyWithTitle(): void
    {
        $data = new OpenGraphData(title: 'My Title');
        self::assertFalse($data->isEmpty());
        self::assertSame('My Title', $data->title);
    }

    public function testNotEmptyWithDescription(): void
    {
        $data = new OpenGraphData(description: 'My Description');
        self::assertFalse($data->isEmpty());
    }

    public function testNotEmptyWithImage(): void
    {
        $data = new OpenGraphData(image: 'image.jpg');
        self::assertFalse($data->isEmpty());
    }

    public function testFullyPopulated(): void
    {
        $data = new OpenGraphData(
            title: 'Title',
            description: 'Description',
            image: 'image.jpg',
        );
        self::assertFalse($data->isEmpty());
        self::assertSame('Title', $data->title);
        self::assertSame('Description', $data->description);
        self::assertSame('image.jpg', $data->image);
    }
}
