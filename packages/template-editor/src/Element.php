<?php

namespace Pushword\TemplateEditor;

use LogicException;

/**
 * Entity.
 */
class Element
{
    protected ?string $path = null;

    protected ?string $code = null;

    protected string $templateDir;

    protected ?string $unlink = null;

    public function __construct(string $templateDir, ?string $path = null)
    {
        $realPathTemplateDir = \Safe\realpath($templateDir);

        $this->templateDir = $realPathTemplateDir;

        if (null !== $path) {
            $this->path = self::normalizePath($path);
        }

        $this->code = $this->loadCode();
    }

    protected function loadCode(): string
    {
        if (null === $this->path || ! file_exists($this->getTemplateDir().$this->getPath())) {
            return '';
        }

        return \Safe\file_get_contents($this->getTemplateDir().$this->getPath());
    }

    protected function getTemplateDir(): string
    {
        return $this->templateDir;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getEncodedPath(): string
    {
        if (null === $this->path) {
            throw new LogicException('the path must be setted before to get the encoded path');
        }

        return md5($this->path);
    }

    private static function normalizePath(string $path): string
    {
        return '/'.ltrim($path, '/');
    }

    public function setPath(string $path): self
    {
        if (str_contains($path, '..')) { // avoiding to store in an other folder than templates.
            throw new \Exception('You can\'t do that...');
        }

        $path = self::normalizePath($path);

        if (null === $this->path) {
            $this->path = $path;

            return $this;
        }

        if ($this->path !== $path) {
            if (file_exists($this->getTemplateDir().$path)) { // check if we don't erase an other file
                throw new \Exception('file ever exist'); // todo move it to assert to avoid error 500..
            }

            // we will delete if we rename it
            if (file_exists($this->getTemplateDir().$this->path)) {
                $this->unlink = $this->getTemplateDir().$this->path;
            }

            $this->path = $path;
        }

        return $this;
    }

    public function getCode(): ?string
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
            unlink($this->unlink);
        }

        return false !== file_put_contents($this->getTemplateDir().$this->path, $this->code);
    }

    public function deleteElement(): bool
    {
        return unlink($this->getTemplateDir().$this->path);
    }
}
