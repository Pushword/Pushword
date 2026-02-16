<?php

namespace Pushword\Core\Controller;

use DateTime;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\RequestContext;

use function Safe\preg_match;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

final readonly class PageResolver
{
    public function __construct(
        private PageRepository $pageRepository,
        private RequestContext $requestContext,
        private Security $security,
    ) {
    }

    public function findPageOr404(Request $request, string $slug, bool $extractPager = false): ?Page
    {
        $slug = self::normalizeSlug($slug);
        $page = $this->pageRepository->getPage($slug, $this->requestContext->getCurrentSite()->getHostForDoctrineSearch());

        if (! $page instanceof Page && $extractPager) {
            $page = $this->extractPager($request, $slug);
        }

        if (! $page instanceof Page) {
            return null;
        }

        if ('' === $page->locale) {
            $page->locale = $this->requestContext->getCurrentSite()->getLocale();
        }

        if ($page->createdAt > new DateTime() && ! $this->security->isGranted('ROLE_EDITOR')) {
            return null;
        }

        $this->requestContext->setCurrentPage($page);

        return $page;
    }

    private function extractPager(Request $request, string &$slug): ?Page
    {
        if (1 !== preg_match('#(/([1-9]\d*)|^([1-9]\d*))$#', $slug, $match)) {
            return null;
        }

        /** @var array{1: string, 2: string, 3:string} $match */
        $unpaginatedSlug = substr($slug, 0, -\strlen($match[1]));
        $request->attributes->set('pager', (int) $match[2] >= 1 ? $match[2] : $match[3]);
        $request->attributes->set('slug', $unpaginatedSlug);

        return $this->findPageOr404($request, $unpaginatedSlug);
    }

    public static function normalizeSlug(?string $slug): string
    {
        return (null === $slug || '' === $slug) ? 'homepage' : rtrim(strtolower($slug), '/');
    }
}
