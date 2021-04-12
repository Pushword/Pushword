<?php

namespace Pushword\PageScanner\Scanner;

use Pushword\Core\Router\RouterInterface as PwRouter;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Utils\GenerateLivePathForTrait;
use Pushword\Core\Utils\KernelTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Permit to find error in image or link.
 */
final class PageScannerService
{
    use GenerateLivePathForTrait;
    use KernelTrait;

    private $errors = [];
    public static $appKernel;

    /** @required */
    public LinkedDocsScanner $linkedDocsScanner;
    /** @required */
    public ParentPageScanner $parentPageScanner;

    public function __construct(
        PwRouter $router, // required for GenerateLivePathForTrait
        KernelInterface $kernel // required for KernelTrait
    ) {
        $this->router = $router;
        $this->router->setUseCustomHostPath(false);

        static::loadKernel($kernel);
        static::$appKernel->getContainer()->get('pushword.router')->setUseCustomHostPath(false);
    }

    private function resetErrors()
    {
        $this->errors = [];
    }

    /**
     * @return true|array
     */
    public function scan(PageInterface $page)
    {
        $this->resetErrors();

        $pageHtml = false === $page->getRedirection() ? $this->getHtml($page, $this->generateLivePathFor($page)) : '';

        $this->addErrors($page, $this->linkedDocsScanner->scan($page, $pageHtml));
        $this->addErrors($page, $this->parentPageScanner->scan($page, $pageHtml));

        return empty($this->errors) ? true : $this->errors;
    }

    private function getHtml(PageInterface $page, string $liveUri): string
    {
        $request = Request::create($liveUri);
        $response = static::$appKernel->handle($request);

        if ($response->isRedirect()) {
            // todo: log: not normal, it must be caught before by doctrine
            return '';
        } elseif (200 != $response->getStatusCode()) {
            file_put_contents('debug', $response);
            $this->addError($page, 'error occured generating the page ('.$response->getStatusCode().')');

            return '';
        }

        return $response->getContent();
    }

    private function addErrors(PageInterface $page, array $messages): void
    {
        foreach ($messages as $message) {
            $this->addError($page, $message);
        }
    }

    private function addError(PageInterface $page, string $message): void
    {
        $this->errors[] = [
            'message' => $message,
            'page' => [
                'id' => $page->getId(),
                'slug' => $page->getSlug(),
                'h1' => $page->getH1(),
                'metaRobots' => $page->getMetaRobots(),
                'host' => $page->getHost(),
            ],
        ];
    }
}
