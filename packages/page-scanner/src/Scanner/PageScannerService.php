<?php

namespace Pushword\PageScanner\Scanner;

use Pushword\Core\Controller\PageController;
use Pushword\Core\Entity\Page;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Twig\MediaExtension;
use Pushword\Core\Utils\TwigErrorExtractor;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Permit to find error in image or link.
 */
final class PageScannerService
{
    /**
     * @var array{message: string, page: array{id:int, slug: string, h1: string, metaRobots: string, host: string}}[]
     */
    private array $errors = [];

    #[Required]
    public LinkedDocsScanner $linkedDocsScanner;

    #[Required]
    public ParentPageScanner $parentPageScanner;

    public function __construct(
        private readonly PushwordRouteGenerator $router,
        private readonly PageController $pageController,
        private readonly TwigErrorExtractor $errorExtractor,
        private readonly MediaExtension $mediaExtension,
    ) {
        $this->router->setUseCustomHostPath(false);
    }

    /**
     * Preload caches to avoid N+1 queries during scanning.
     * Call this before scanning multiple pages.
     *
     * @param string $host If provided, only preload pages from this host
     */
    public function preloadCaches(string $host = ''): void
    {
        $this->mediaExtension->preloadMediaCache();
        $this->linkedDocsScanner->preloadPageCache($host);
    }

    private function resetErrors(): void
    {
        $this->errors = [];
    }

    /**
     * @return array{message: string, page: array{id:int, slug: string, h1: string, metaRobots: string, host: string}}[]|true
     */
    public function scan(Page $page): array|bool
    {
        $this->resetErrors();

        $pageHtml = $page->hasRedirection() ? '' : $this->getHtml($page);

        $this->addErrors($page, $this->linkedDocsScanner->scan($page, $pageHtml));
        $this->addErrors($page, $this->parentPageScanner->scan($page, $pageHtml));

        return [] === $this->errors ? true : $this->errors;
    }

    private function getHtml(Page $page): string
    {
        try {
            $this->pageController->setHost($page->host);
            $response = $this->pageController->showPage($page);

            if ($response->isRedirect()) {
                return '';
            }

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                $this->addError($page, sprintf('error occurred generating the page (%d)', $response->getStatusCode()));

                return '';
            }

            $content = $response->getContent();
            if (false === $content) {
                $this->addError($page, 'error occurred generating the page (empty response)');

                return '';
            }

            return $content;
        } catch (RuntimeError|SyntaxError $twigError) {
            $this->addError($page, $this->errorExtractor->formatErrorMessage($twigError));

            return '';
        } catch (Throwable $exception) {
            $this->addError($page, $this->errorExtractor->formatGenericErrorMessage($exception));

            return '';
        }
    }

    /**
     * @param string[] $messages
     */
    private function addErrors(Page $page, array $messages): void
    {
        foreach ($messages as $message) {
            $this->addError($page, $message);
        }
    }

    private function addError(Page $page, string $message): void
    {
        $this->errors[] = [
            'message' => $message,
            'page' => [
                'id' => $page->id ?? 0,
                'slug' => $page->getSlug(),
                'h1' => $page->getH1(),
                'metaRobots' => $page->getMetaRobots(),
                'host' => $page->host,
            ],
        ];
    }
}
