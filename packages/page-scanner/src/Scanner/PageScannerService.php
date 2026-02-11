<?php

namespace Pushword\PageScanner\Scanner;

use LogicException;
use Pushword\Core\Controller\PageController;
use Pushword\Core\Entity\Page;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Site\RequestContext;
use Pushword\Core\Twig\MediaExtension;
use Pushword\Core\Utils\TwigErrorExtractor;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;
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

    /** @var DataCollectorTranslator|Translator */
    private readonly TranslatorInterface $translator;

    public function __construct(
        private readonly PushwordRouteGenerator $router,
        private readonly PageController $pageController,
        private readonly TwigErrorExtractor $errorExtractor,
        private readonly MediaExtension $mediaExtension,
        private readonly RequestContext $requestContext,
        TranslatorInterface $translator,
    ) {
        if (! $translator instanceof DataCollectorTranslator && ! $translator instanceof Translator) {
            throw new LogicException('Expected DataCollectorTranslator or Translator, got '.$translator::class);
        }

        $this->translator = $translator;
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
            $this->requestContext->setCurrentPage($page);
            $this->translator->setLocale('' !== $page->locale ? $page->locale : $this->requestContext->getCurrentSite()->getLocale());
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
