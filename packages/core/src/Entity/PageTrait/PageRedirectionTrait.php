<?php

namespace Pushword\Core\Entity\PageTrait;

use Pushword\Core\Utils\F;

trait PageRedirectionTrait
{
    /**
     * @var string|false|null
     */
    protected $redirectionUrl;

    protected ?int $redirectionCode = null;

    abstract public function getMainContent(): string;

    /**
     * Check if a content don't start by 'Location: http://valid-url.tld/eg'.
     */
    protected function manageRedirection(): void
    {
        $content = $this->getMainContent();
        $code = 301; // default symfony is 302...
        if (str_starts_with($content, 'Location:')) {
            $url = trim(substr($content, 9));
            if (1 === \Safe\preg_match('/ [1-5]\d{2}$/', $url, $match)) {
                $code = (int) trim((string) $match[0]);
                $url = F::preg_replace_str('/ [1-5]\d{2}$/', '', $url);
            }

            if (false !== filter_var($url, \FILTER_VALIDATE_URL) || 1 === \Safe\preg_match('/^[^ ]+$/', $url)) {
                $this->redirectionUrl = $url;
                $this->redirectionCode = $code;

                return;
            }
        }

        $this->redirectionUrl = false;
    }

    public function hasRedirection(): bool
    {
        if (null === $this->redirectionUrl) {
            $this->manageRedirection();
        }

        return false !== $this->redirectionUrl;
    }

    /** @psalm-suppress InvalidNullableReturnType */
    public function getRedirection(): string
    {
        if (null === $this->redirectionUrl) {
            $this->manageRedirection();
        }

        if (false === $this->redirectionUrl) {
            throw new \LogicException('You may check a redirection exist before to get the redirection url');
        }

        // @phpstan-ignore-next-line
        return $this->redirectionUrl;
    }

    public function getRedirectionCode(): int
    {
        if (null === $this->redirectionCode) {
            throw new \LogicException('You may check a redirection exist before to get the redirection code');
        }

        return $this->redirectionCode;
    }
}
