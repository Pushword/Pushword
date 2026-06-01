<?php

namespace Pushword\Core\Entity\PageTrait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\Page;

/**
 * Internal redirects authored on the destination page (Jekyll `redirect_from` style):
 * a map of old paths (on the same host) to the HTTP code that should redirect them here.
 *
 * Stored shape: { "old-slug": 301, "old/other": 302 }. Always normalized + ksorted on set
 * so flat export stays idempotent. Served by PageRepository's reverse redirect map.
 */
trait RedirectFromTrait
{
    /** @var array<string, int> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    protected array $redirectFrom = [];

    /** @return array<string, int> normalized path => http code */
    public function getRedirectFrom(): array
    {
        return $this->redirectFrom;
    }

    /** @return array<string, int> alias used by the runtime/static redirect layer */
    public function getRedirectFromMap(): array
    {
        return $this->redirectFrom;
    }

    /**
     * Accepts a map ({path: code}), a Jekyll-style list ([path, …], all 301),
     * a list of rows ([{from: path, code: 301}, …]) or a whitespace/comma string.
     *
     * @param array<mixed>|string|null $value
     */
    public function setRedirectFrom(array|string|null $value): self
    {
        $this->redirectFrom = self::normalizeRedirectFrom($value);

        return $this;
    }

    public function addRedirectFrom(string $path, int $code = 301): self
    {
        $this->setRedirectFrom([...$this->redirectFrom, $path => $code]);

        return $this;
    }

    /**
     * Row view of the map ([{from, code}, …]) for the admin collection editor.
     *
     * @return list<array{from: string, code: int}>
     */
    public function getRedirectFromRows(): array
    {
        $rows = [];
        foreach ($this->redirectFrom as $from => $code) {
            $rows[] = ['from' => $from, 'code' => $code];
        }

        return $rows;
    }

    /**
     * @param array<mixed>|null $rows
     */
    public function setRedirectFromRows(?array $rows): self
    {
        return $this->setRedirectFrom($rows ?? []);
    }

    /**
     * @param array<mixed>|string|null $value
     *
     * @return array<string, int>
     */
    private static function normalizeRedirectFrom(array|string|null $value): array
    {
        if (null === $value || '' === $value || [] === $value) {
            return [];
        }

        if (\is_string($value)) {
            $value = preg_split('/[\s,]+/', trim($value)) ?: [];
        }

        $result = [];
        foreach ($value as $key => $entry) {
            if (\is_string($key)) { // map form: path => code
                $path = $key;
                $code = $entry;
            } elseif (\is_array($entry)) { // row form: ['from' => path, 'code' => code]
                $path = $entry['from'] ?? $entry['path'] ?? $entry['slug'] ?? '';
                $code = $entry['code'] ?? 301;
            } else { // list form: bare path, implicit 301
                $path = $entry;
                $code = 301;
            }

            if (! \is_string($path)) {
                continue;
            }

            if ('' === $path) {
                continue;
            }

            $path = Page::normalizeSlug($path);
            if ('' === $path) {
                continue;
            }

            $code = is_numeric($code) ? (int) $code : 301;
            $result[$path] = $code >= 300 && $code <= 399 ? $code : 301;
        }

        ksort($result);

        return $result;
    }
}
