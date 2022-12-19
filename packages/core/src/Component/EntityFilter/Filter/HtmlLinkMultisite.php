<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\AutowiringTrait\RequiredApps;
use Pushword\Core\AutowiringTrait\RequiredAppTrait;
use Pushword\Core\AutowiringTrait\RequiredTwigTrait;
use Pushword\Core\Repository\Repository;
use Pushword\Core\Router\RouterInterface;
use Pushword\Core\Twig\LinkTwigTrait;

final class HtmlLinkMultisite extends AbstractFilter
{
    use LinkTwigTrait;
    use RequiredApps;
    use RequiredAppTrait;
    use RequiredTwigTrait;

    private EntityManagerInterface $entityManager;

    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    private RouterInterface $router;

    public function setRouter(RouterInterface $router): void
    {
        $this->router = $router;
    }

    /**
     * @var string
     */
    public const HTML_REGEX = '/href=((?P<hrefQuote>\'|")(?P<href1>(?:(?!(?P=hrefQuote)).)*)(?P=hrefQuote)|(?P<href2>[^"\'>][^> \r\n\t\f\v]*))/iJ';

    /** @var string */
    public const HTML_REGEX_HREF_KEY = 'href';

    public function apply(mixed $propertyValue): string
    {
        $propertyValue = \strval($propertyValue);
        if (! method_exists($this->router, 'mayUseCustomPath')) {
            return $propertyValue;
        }

        if (! $this->router->mayUseCustomPath()) {
            return $propertyValue;
        }

        return $this->convertLinks($propertyValue);
    }

    public function convertLinks(string $body): string
    {
        \Safe\preg_match_all(self::HTML_REGEX, $body, $matches);

        if (! isset($matches[1])) {
            return $body;
        }

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
        if (($page = Repository::getPageRepository($this->entityManager, $currentPage::class)
            ->findOneBy(['slug' => $newHref, 'host' => $currentPage->getHost()])) !== null) {
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

        throw new \Exception();
    }
}
