<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Exception;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Router\PushwordRouteGenerator;

use function Safe\preg_match_all;

#[AsFilter]
final readonly class HtmlLinkMultisite implements FilterInterface
{
    /**
     * @var string
     */
    public const string HTML_REGEX = '/href=((?P<hrefQuote>\'|")(?P<href1>(?:(?!(?P=hrefQuote)).)+)(?P=hrefQuote)|(?P<href2>[^"\'>][^> \r\n\t\f\v]*))/iJ';

    /** @var string */
    public const string HTML_REGEX_HREF_KEY = 'href';

    public function __construct(
        private PushwordRouteGenerator $router,
        private AppPool $apps,
        private PageRepository $pageRepository,
    ) {
    }

    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        assert(is_scalar($propertyValue));
        $propertyValue = (string) $propertyValue;

        if (! $this->router->mayUseCustomPath()) {
            return $propertyValue;
        }

        return $this->convertLinks($propertyValue);
    }

    public function convertLinks(string $body): string
    {
        preg_match_all(self::HTML_REGEX, $body, $matches);

        if (! isset($matches[1])) {
            return $body;
        }

        /** @var array<int|string, array<int, string>> $matches */

        return $this->replaceLinks($body, $matches);
    }

    /**
     * @param array<(string|int), array<int, string>> $matches
     */
    private function replaceLinks(string $body, array $matches): string
    {
        $nbrMatch = \count($matches[0]);
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $currentHref = $this->getHrefValue($matches, $k);
            $newHref = $this->getHref($currentHref);
            if ($newHref !== $currentHref) {
                $body = str_replace(
                    $matches[0][$k],
                    'href='.($matches['hrefQuote'][$k] ?? '"').$newHref.($matches['hrefQuote'][$k] ?? '"'),
                    $body
                );
            }
        }

        return $body;
    }

    private function getHref(string $href): string
    {
        if (! str_starts_with($href, '/')) {
            return $href;
        }

        if (($currentPage = $this->apps->getCurrentPage()) === null) {
            return $href;
        }

        $slug = $this->extractSlug($href);
        $hrefHashPart = '';

        $hashPos = strpos($href, '#');
        if (false !== $hashPos) {
            $hrefHashPart = substr($href, $hashPos);
        }

        if ('' === $slug) {
            return $href;
        }

        $page = $this->pageRepository->getPageBySlug($slug, $currentPage->getHost());

        if (null === $page) {
            return $href;
        }

        return $this->router->generate($page).$hrefHashPart;
    }

    private function extractSlug(string $href): string
    {
        $slug = substr($href, 1);
        $hashPos = strpos($slug, '#');
        if (false !== $hashPos) {
            return substr($slug, 0, $hashPos);
        }

        return $slug;
    }

    /**
     * @param array<(string|int), array<int, string>> $matches
     */
    private function getHrefValue(array $matches, int $k): string
    {
        for ($i = 1; $i <= 2; ++$i) {
            if ('' !== $matches[self::HTML_REGEX_HREF_KEY.$i][$k]) {
                return $matches[self::HTML_REGEX_HREF_KEY.$i][$k];
            }
        }

        throw new Exception();
    }
}
