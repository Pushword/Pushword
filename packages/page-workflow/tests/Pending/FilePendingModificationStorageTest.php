<?php

namespace Pushword\PageWorkflow\Tests\Pending;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Page;
use Pushword\PageWorkflow\Pending\FilePendingModificationStorage;
use Pushword\PageWorkflow\Pending\PendingModification;
use ReflectionProperty;
use Symfony\Component\Filesystem\Filesystem;

final class FilePendingModificationStorageTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/pushword-pending-test-'.uniqid();
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->tmpDir);
    }

    public function testWriteThenReadReturnsEquivalentModification(): void
    {
        $page = $this->makePage(42);
        $storage = new FilePendingModificationStorage($this->tmpDir);

        $modification = new PendingModification(
            pageId: 42,
            payload: ['h1' => 'New title', 'mainContent' => 'New body'],
        );
        $modification->workflowState = 'in_review';
        $modification->editedBy = 7;
        $modification->editMessage = 'Quick fix';

        $storage->write($page, $modification);
        $loaded = $storage->read($page);

        self::assertNotNull($loaded);
        self::assertSame(42, $loaded->pageId);
        self::assertSame('in_review', $loaded->workflowState);
        self::assertSame(7, $loaded->editedBy);
        self::assertSame('Quick fix', $loaded->editMessage);
        self::assertSame(['h1' => 'New title', 'mainContent' => 'New body'], $loaded->payload);
    }

    public function testReadMissingReturnsNull(): void
    {
        $page = $this->makePage(99);
        $storage = new FilePendingModificationStorage($this->tmpDir);

        self::assertFalse($storage->has($page));
        self::assertNull($storage->read($page));
    }

    public function testDeleteRemovesTheSnapshot(): void
    {
        $page = $this->makePage(13);
        $storage = new FilePendingModificationStorage($this->tmpDir);

        $storage->write($page, new PendingModification(pageId: 13));
        self::assertTrue($storage->has($page));

        $storage->delete($page);
        self::assertFalse($storage->has($page));
    }

    private function makePage(int $id): Page
    {
        $page = new Page();
        // Page.id is hooked; set via reflection to bypass Doctrine flow.
        $refl = new ReflectionProperty(Page::class, 'id');
        $refl->setValue($page, $id);

        return $page;
    }
}
