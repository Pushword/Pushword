<?php

namespace Pushword\PageScanner\Scanner;

use Pushword\Core\Entity\Page;
use Pushword\PageScanner\Service\ErrorIgnoreRules;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Permit to find error in image or link.
 */
abstract class AbstractScanner
{
    protected Page $page;

    protected string $pageHtml;

    /**
     * @var array<array{code: string, message: string}>
     */
    protected array $errors = [];

    /**
     * What the page being scanned asked not to be told about.
     *
     * @var string[]
     */
    protected array $pageIgnorePatterns = [];

    public function __construct(
        protected readonly TranslatorInterface $translator
    ) {
    }

    public function addError(ScanErrorCode $code, string $msg): void
    {
        if (ErrorIgnoreRules::matches($this->pageIgnorePatterns, $code->value, $msg)) {
            return;
        }

        $this->errors[] = ['code' => $code->value, 'message' => $msg];
    }

    /** @return array<array{code: string, message: string}> */
    public function scan(Page $page, string $pageHtml): array
    {
        $this->errors = [];
        $this->page = $page;
        $this->pageHtml = $pageHtml;
        $this->pageIgnorePatterns = ErrorIgnoreRules::forPage($page);

        $this->run();

        return $this->errors;
    }

    abstract protected function run(): void;

    /**
     * Undocumented function.
     *
     * @param mixed[] $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $this->translator->trans($id, $parameters, $domain, $locale);
    }
}
