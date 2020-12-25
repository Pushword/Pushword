<?php

namespace Pushword\PageScanner\Scanner;

use Pushword\Core\Entity\Page;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Utils\GenerateLivePathForTrait;
use Pushword\Core\Utils\KernelTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Permit to find error in image or link.
 *
 *  @psalm-suppress PropertyNotSetInConstructor */
final class PageScannerService
{
    use GenerateLivePathForTrait;
    use KernelTrait;

    /**
     * @var mixed[]
     */
    private array $errors = [];

    /** @psalm-suppress PropertyNotSetInConstructor */
    #[Required]
    public LinkedDocsScanner $linkedDocsScanner;

    /** @psalm-suppress PropertyNotSetInConstructor */
    #[Required]
    public ParentPageScanner $parentPageScanner;

    public function __construct(
        PushwordRouteGenerator $pwRouter, // required for GenerateLivePathForTrait
        KernelInterface $kernel,// required for KernelTrait
        // private readonly PushwordRouteGenerator $pushwordRouteGenerator,
    ) {
        $this->router = $pwRouter;
        $this->router->setUseCustomHostPath(false);

        static::loadKernel($kernel);
        static::getKernel()->getContainer()->get(PushwordRouteGenerator::class)->setUseCustomHostPath(false);
    }

    private function resetErrors(): void
    {
        $this->errors = [];
    }

    /**
     * @return mixed[]|true
     *
     * @noRector
     */
    public function scan(Page $page): array|bool
    {
        $this->resetErrors();

        $pageHtml = $page->hasRedirection() ? '' : $this->getHtml($page, $this->generateLivePathFor($page));

        $this->addErrors($page, $this->linkedDocsScanner->scan($page, $pageHtml));
        $this->addErrors($page, $this->parentPageScanner->scan($page, $pageHtml));

        return [] === $this->errors ? true : $this->errors;
    }

    private function getHtml(Page $page, string $liveUri): string
    {
        $request = Request::create($liveUri);
        $response = static::getKernel()->handle($request);

        if ($response->isRedirect()) {
            // todo: log: not normal, it must be caught before by doctrine
            return '';
        }

        if (false === ($content = $response->getContent()) || Response::HTTP_OK != $response->getStatusCode()) {
            $this->addError($page, 'error occured generating the page ('.$response->getStatusCode().')');

            return '';
        }

        return $content;
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
                'id' => $page->getId(),
                'slug' => $page->getSlug(),
                'h1' => $page->getH1(),
                'metaRobots' => $page->getMetaRobots(),
                'host' => $page->getHost(),
            ],
        ];
    }
}
