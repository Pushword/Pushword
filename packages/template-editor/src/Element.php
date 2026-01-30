<?php

namespace Pushword\TemplateEditor;

use Exception;
use LogicException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Entity.
 */
final class Element
{
    private string $code;

    private ?string $unlink = null;

    private readonly Filesystem $filesystem;

    public function __construct(
        private string $templateDir,
        private ?string $path = null,
        private readonly bool $disableMoving = false
    ) {
        $this->filesystem = new Filesystem();

        $realPathTemplateDir = realpath($templateDir);
        if (false === $realPathTemplateDir) {
            throw new LogicException(\sprintf('Template directory does not exist: %s', $templateDir));
        }

        $this->templateDir = $realPathTemplateDir;

        if (null !== $path) {
            $this->path = $this->normalizePath($path);
        }

        $this->code = $this->retrieveCode();
    }

    private function retrieveCode(): string
    {
        if (null === $this->path) {
            return '';
        }

        if (! $this->filesystem->exists($this->getTemplateDir().$this->getPath())) {
            return '';
        }

        return $this->filesystem->readFile($this->getTemplateDir().$this->getPath());
    }

    private function getTemplateDir(): string
    {
        return $this->templateDir;
    }

    public function getPath(): string
    {
        return $this->path ?? '';
    }

    public function getEncodedPath(): string
    {
        if (null === $this->path) {
            throw new LogicException('the path must be setted before to get the encoded path');
        }

        return md5($this->path);
    }

    private function normalizePath(string $path): string
    {
        return '/'.ltrim($path, '/');
    }

    public function setPath(string $path): self
    {
        if ($this->disableMoving) {
            return $this;
        }

        if (str_contains($path, '..')) { // avoiding to store in an other folder than templates.
            throw new Exception("You can't do that...");
        }

        $path = $this->normalizePath($path);

        if (null === $this->path) {
            $this->path = $path;

            return $this;
        }

        if ($this->path !== $path) {
            if ($this->filesystem->exists($this->getTemplateDir().$path)) { // check if we don't erase an other file
                throw new Exception('file ever exist'); // todo move it to assert to avoid error 500..
            }

            // we will delete if we rename it
            if ($this->filesystem->exists($this->getTemplateDir().$this->path)) {
                $this->unlink = $this->getTemplateDir().$this->path;
            }

            $this->path = $path;
        }

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function storeElement(): bool
    {
        if (null !== $this->unlink) { // for rename
            $this->filesystem->remove($this->unlink);
        }

        $this->filesystem->dumpFile($this->getTemplateDir().$this->getPath(), $this->code);

        return true;
    }

    public function deleteElement(): bool
    {
        $this->filesystem->remove($this->getTemplateDir().$this->getPath());

        return true;
    }

    public function movingIsDisabled(): bool
    {
        return $this->disableMoving;
    }
}
