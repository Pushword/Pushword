<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Service\LinkProvider;

use function Safe\preg_match_all;

use Twig\Environment;

final class HtmlLinkMultisite extends AbstractFilter
{
    public LinkProvider $linkProvider;

    public AppPool $apps;

    public AppConfig $app;

    public Environment $twig;

    public EntityManagerInterface $entityManager;

    public PushwordRouteGenerator $router;

    /**
     * @var string
     */
    public const string HTML_REGEX = '/href=((?P<hrefQuote>\'|")(?P<href1>(?:(?!(?P=hrefQuote)).)*)(?P=hrefQuote)|(?P<href2>[^"\'>][^> \r\n\t\f\v]*))/iJ';

    /** @var string */
    public const string HTML_REGEX_HREF_KEY = 'href';

    public function apply(mixed $propertyValue): string
    {
        $propertyValue = $this->string($propertyValue);
        // if (! $this->router instanceof PushwordRouteGenerator) { return $propertyValue; }

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

        $newHref = substr($href, 1);
        $page = $this->entityManager->getRepository(Page::class)->findOneBy(['slug' => $newHref, 'host' => $currentPage->getHost()]);
        if (null !== $page) {
            return $this->router->generate($page);
        }

        return $href;
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
