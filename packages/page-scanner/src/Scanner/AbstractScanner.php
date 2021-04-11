<?php

namespace Pushword\PageScanner\Scanner;

use Pushword\Core\Entity\PageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Permit to find error in image or link.
 */
abstract class AbstractScanner
{
    protected PageInterface $page;
    protected string $pageHtml;
    protected array $errors = [];

    /** @required */
    public TranslatorInterface $translator;

    public function addError(string $msg): void
    {
        $this->errors[] = $msg;
    }

    public function scan(PageInterface $page, string $pageHtml): array
    {
        $this->errors = [];
        $this->page = $page;
        $this->pageHtml = $pageHtml;

        $this->run();

        return $this->errors;
    }

    abstract protected function run(): void;

    public function trans(string $id, array $parameters = [], string $domain = null, string $locale = null): string
    {
        return $this->translator->trans($id, $parameters, $domain, $locale);
    }
}
