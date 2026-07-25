<?php

namespace Pushword\Search\Tests;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Search\Event\SearchDocumentEvent;
use Pushword\StaticGenerator\Event\StaticPostGenerateEvent;

use function Safe\json_decode;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Group('integration')]
final class StaticSearchSubscriberTest extends KernelTestCase
{
    private const int JSON_CONTENT_LENGTH = 1000;

    public function testEmitsSearchJsonAndIndexDb(): void
    {
        $dir = $this->generateStatic();

        try {
            self::assertFileExists($dir.'/search.json');
            self::assertFileExists($dir.'/search/loupe.db');

            // simple-jekyll-search calls .trim() on every field value, so each
            // one must be a string — an array (e.g. tags) breaks client search.
            foreach ($this->readSearchJson($dir) as $entry) {
                self::assertIsArray($entry);
                foreach ($entry as $value) {
                    self::assertIsString($value);
                }
            }
        } finally {
            new Filesystem()->remove($dir);
        }
    }

    /**
     * SearchDocumentEvent listeners receive `mixed` and may overwrite a core
     * field. Loupe's schema catches that on searchable/filterable attributes,
     * but `url` and `slug` are neither, so they reach search.json unchecked —
     * coerce them, since simple-jekyll-search calls .trim() on every value.
     */
    public function testCoercesNonStringFieldsSetByAListener(): void
    {
        $dir = $this->generateStatic(static function (SearchDocumentEvent $searchDocumentEvent): void {
            $searchDocumentEvent->setAttribute('url', 42);
            $searchDocumentEvent->setAttribute('slug', ['broken']);
        });

        try {
            foreach ($this->readSearchJson($dir) as $entry) {
                self::assertIsArray($entry);
                self::assertSame('42', $entry['url']);
                self::assertSame('', $entry['slug']);
            }
        } finally {
            new Filesystem()->remove($dir);
        }
    }

    public function testFlattensTagsAndTruncatesContent(): void
    {
        $dir = $this->generateStatic(static function (SearchDocumentEvent $searchDocumentEvent): void {
            $searchDocumentEvent->setAttribute('tags', ['alpha', 'beta']);
            $searchDocumentEvent->setAttribute('content', str_repeat('a', 2 * self::JSON_CONTENT_LENGTH));
        });

        try {
            foreach ($this->readSearchJson($dir) as $entry) {
                self::assertIsArray($entry);
                self::assertSame('alpha beta', $entry['tags']);
                self::assertIsString($entry['content']);
                self::assertSame(self::JSON_CONTENT_LENGTH, mb_strlen($entry['content']));
            }
        } finally {
            new Filesystem()->remove($dir);
        }
    }

    /**
     * A generation that reported errors would ship a search index built from a
     * broken render, so nothing is written at all.
     */
    public function testSkipsAFailedGeneration(): void
    {
        $dir = $this->generateStatic(null, ['/broken-page']);

        try {
            self::assertFileDoesNotExist($dir.'/search.json');
            self::assertFileDoesNotExist($dir.'/search/loupe.db');
        } finally {
            new Filesystem()->remove($dir);
        }
    }

    /**
     * @param ?callable(SearchDocumentEvent): void $documentListener
     * @param array<string>                        $errors
     *
     * @return string the static directory the subscriber wrote into
     */
    private function generateStatic(?callable $documentListener = null, array $errors = []): string
    {
        self::bootKernel();
        $container = self::getContainer();
        $dispatcher = $container->get(EventDispatcherInterface::class);
        $app = $container->get(SiteRegistry::class)->get('localhost.dev');

        if (null !== $documentListener) {
            $dispatcher->addListener(SearchDocumentEvent::class, $documentListener);
        }

        $dir = sys_get_temp_dir().'/pw-search-static-'.uniqid();
        new Filesystem()->mkdir($dir);

        $dispatcher->dispatch(new StaticPostGenerateEvent($app, $dir, false, $errors));

        return $dir;
    }

    /**
     * @return array<mixed> the decoded search.json entries
     */
    private function readSearchJson(string $dir): array
    {
        $json = json_decode((string) file_get_contents($dir.'/search.json'), true);

        self::assertIsArray($json);
        self::assertNotEmpty($json);

        return $json;
    }
}
