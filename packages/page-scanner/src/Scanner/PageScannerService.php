<?php

namespace Pushword\PageScanner\Scanner;

use LogicException;
use Pushword\Core\Controller\PageController;
use Pushword\Core\Entity\Page;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Site\RequestContext;
use Pushword\Core\Twig\MediaExtension;
use Pushword\Core\Utils\TwigErrorExtractor;
use Pushword\PageScanner\Service\ErrorIgnoreRules;
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
     * @var array{code: string, message: string, page: array{id:int, slug: string, h1: string, metaRobots: string, host: string}}[]
     */
    private array $errors = [];

    /** @var string[] What the page being scanned asked not to be told about. */
    private array $pageIgnorePatterns = [];

    #[Required]
    public LinkedDocsScanner $linkedDocsScanner;

    #[Required]
    public ParentPageScanner $parentPageScanner;

    #[Required]
    public TodoScanner $todoScanner;

    #[Required]
    public BrokenImageScanner $brokenImageScanner;

    #[Required]
    public TwigErrorScanner $twigErrorScanner;

    #[Required]
    public DateShortcodeScanner $dateShortcodeScanner;

    #[Required]
    public MissingAltScanner $missingAltScanner;

    #[Required]
    public TranslationLocaleScanner $translationLocaleScanner;

    #[Required]
    public LinkGraphScanner $linkGraphScanner;

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
     */
    public function preloadCaches(): void
    {
        $this->mediaExtension->preloadMediaCache();
        $this->linkedDocsScanner->preloadPageCache();
    }

    /**
     * @return array{code: string, message: string, page: array{id:int, slug: string, h1: string, metaRobots: string, host: string}}[]|true
     */
    public function scan(Page $page): array|bool
    {
        $this->errors = [];
        $this->pageIgnorePatterns = ErrorIgnoreRules::forPage($page);

        $pageHtml = $page->hasRedirection() ? '' : $this->getHtml($page);

        $this->addErrors($page, $this->linkedDocsScanner->scan($page, $pageHtml));
        $this->addErrors($page, $this->parentPageScanner->scan($page, $pageHtml));
        $this->addErrors($page, $this->todoScanner->scan($page, $pageHtml));
        $this->addErrors($page, $this->brokenImageScanner->scan($page, $pageHtml));
        $this->addErrors($page, $this->twigErrorScanner->scan($page, $pageHtml));
        $this->addErrors($page, $this->dateShortcodeScanner->scan($page, $pageHtml));
        $this->addErrors($page, $this->missingAltScanner->scan($page, $pageHtml));
        $this->addErrors($page, $this->translationLocaleScanner->scan($page, $pageHtml));

        // Reports nothing: it only rides the loop to collect the link graph from
        // the HTML we just rendered. Call reset() before scanning a page set.
        $this->linkGraphScanner->scan($page, $pageHtml);

        return [] === $this->errors ? true : $this->errors;
    }

    private function getHtml(Page $page): string
    {
        try {
            $this->pageController->setHost($page->host);
            $this->requestContext->currentPage = $page;
            $this->translator->setLocale('' !== $page->locale ? $page->locale : $this->requestContext->currentSite->locale);
            $response = $this->pageController->showPage($page);

            if ($response->isRedirect()) {
                return '';
            }

            if (Response::HTTP_OK !== $response->getStatusCode()) {
                $this->addRenderError($page, sprintf('error occurred generating the page (%d)', $response->getStatusCode()));

                return '';
            }

            $content = $response->getContent();
            if (false === $content) {
                $this->addRenderError($page, 'error occurred generating the page (empty response)');

                return '';
            }

            return $content;
        } catch (RuntimeError|SyntaxError $twigError) {
            $this->addRenderError($page, $this->errorExtractor->formatErrorMessage($twigError));

            return '';
        } catch (Throwable $exception) {
            $this->addRenderError($page, $this->errorExtractor->formatGenericErrorMessage($exception));

            return '';
        }
    }

    /**
     * @param array<array{code: string, message: string}> $errors the scanners already
     *                                                            dropped what the page asked to ignore
     */
    private function addErrors(Page $page, array $errors): void
    {
        foreach ($errors as $error) {
            $this->errors[] = $this->record($page, $error['code'], $error['message']);
        }
    }

    /**
     * The only finding this service raises itself: the page did not render, so
     * every scanner below it was handed an empty HTML.
     */
    private function addRenderError(Page $page, string $message): void
    {
        if (ErrorIgnoreRules::matches($this->pageIgnorePatterns, ScanErrorCode::RenderError->value, $message)) {
            return;
        }

        $this->errors[] = $this->record($page, ScanErrorCode::RenderError->value, $message);
    }

    /**
     * @return array{code: string, message: string, page: array{id:int, slug: string, h1: string, metaRobots: string, host: string}}
     */
    private function record(Page $page, string $code, string $message): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'page' => [
                'id' => $page->id ?? 0,
                'slug' => $page->slug,
                'h1' => $page->h1,
                'metaRobots' => $page->metaRobots,
                'host' => $page->host,
            ],
        ];
    }
}
