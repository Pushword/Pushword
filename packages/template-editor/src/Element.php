<?php

namespace Pushword\TemplateEditor;

/**
 * Entity.
 */
class Element
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $code;

    /**
     * @var string
     */
    protected $templateDir;

    /**
     * @var string
     */
    protected $unlink;

    public function __construct($templateDir, $path = null)
    {
        $this->templateDir = realpath($templateDir);
        if (null !== $path) {
            $this->path = self::normalizePath($path);
        }
        $this->code = $this->loadCode();
    }

    protected function loadCode(): string
    {
        if ($this->path && file_exists($this->getTemplateDir().$this->getPath())) {
            return file_get_contents($this->getTemplateDir().$this->getPath());
        }

        return '';
    }

    protected function getTemplateDir(): string
    {
        return $this->templateDir;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getEncodedPath(): ?string
    {
        return md5($this->path);
    }

    private static function normalizePath($path): string
    {
        return '/'.ltrim($path, '/');
    }

    public function setPath(string $path): self
    {
        if (false !== strpos($path, '..')) { // avoiding to store in an other folder than templates.
            throw new \Exception('You can\'t do that...');
        }

        $path = self::normalizePath($path);

        if (null === $this->path) {
            $this->path = $path;
        } else {
            if ($this->path != $path) {
                if (file_exists($this->getTemplateDir().$path)) { // check if we don't erase an other file
                    throw new \Exception('file ever exist'); // todo move it to assert to avoid error 500..
                } else {
                    // we will delete if we rename it
                    if ($this->path && file_exists($this->getTemplateDir().$this->path)) {
                        $this->unlink = $this->getTemplateDir().$this->path;
                    }
                    $this->path = $path;
                }
            }
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
        if ($this->unlink) { // for rename
            unlink($this->unlink);
        }

        return false !== file_put_contents($this->getTemplateDir().$this->path, $this->code) ? true : false;
    }

    public function deleteElement(): bool
    {
        return unlink($this->getTemplateDir().$this->path);
    }
}
