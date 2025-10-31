<?php

namespace Pushword\Flat\Exporter;

use DateTimeInterface;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Utils\Entity;
use ReflectionClass;
use Stringable;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

final class PageExporter
{
    public string $exportDir = '';

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly AppPool $apps,
        private readonly PageRepository $pageRepo,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function exportPages(bool $force = false): void
    {
        $pages = $this->pageRepo->findByHost($this->apps->get()->getMainHost());

        foreach ($pages as $page) {
            // compare the filemtime with the page->getUpdatedAt()
            $this->exportPage($page, $force);
        }
    }

    private function exportPage(Page $page, bool $force = false): void
    {
        $exportFilePath = $this->exportDir.'/'.$page->getSlug().'.md';
        if (
            false === $force
            && file_exists($exportFilePath)
            && filemtime($exportFilePath) >= $page->safegetUpdatedAt()->getTimestamp()
        ) {
            return;
        }

        $properties = ['title', 'h1', 'slug', 'id'] + Entity::getProperties($page);

        $data = [];
        foreach ($properties as $property) {
            $value = $this->getValue($property, $page);
            if (null === $value) {
                continue;
            }

            $data[$property] = $value;
        }

        $metaData = Yaml::dump($data, indent: 2);
        $content = '---'.\PHP_EOL.$metaData.'---'.\PHP_EOL.\PHP_EOL.$page->getMainContent();

        $this->filesystem->dumpFile($exportFilePath, $content);
    }

    /**
     * @return scalar|null
     */
    private function getValue(string $property, Page $page): mixed
    {
        if ('mainContent' === $property) {
            return null;
        }

        $getter = 'get'.ucfirst($property);
        $value = $page->$getter(); // @phpstan-ignore-line

        if ($value instanceof Page) {
            $value = $page->getSlug();
        }

        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        if (null === $value) {
            return null;
        }

        if (
            in_array($property, ['customProperties', 'tags'], true)
            && in_array($value, [null, [], ''], true)
        ) {
            return null;
        }

        if ($value === $this->getPageDefaultValue($property)) {
            return null;
        }

        if (in_array($property, ['createdAt', 'updatedAt', 'host', 'slug'], true)) {
            return null;
        }

        if ('locale' === $property && $value === $this->apps->get()->getLocale()) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i');
        }

        assert(is_scalar($value));

        return $value;
    }

    /** @var array<string, mixed> */
    private array $defaultValueCache = [];

    private function getPageDefaultValue(string $property): mixed
    {
        if (isset($this->defaultValueCache[$property])) {
            return $this->defaultValueCache[$property];
        }

        $reflection = new ReflectionClass(Page::class);
        $reflectionProperty = $reflection->getProperty($property);
        $this->defaultValueCache[$property] = $reflectionProperty->getDefaultValue();

        return $this->defaultValueCache[$property];
    }
}
