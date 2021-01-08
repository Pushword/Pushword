<?php

namespace Pushword\StaticGenerator\Generator;

use Exception;
use Pushword\Core\Entity\PageInterface as Page;
use Symfony\Component\HttpFoundation\Request;

class PageGenerator extends AbstractGenerator
{
    /** @required */
    public RedirectionManager $redirectionManager;

    public function generate(?string $host = null): void
    {
        parent::generate($host);

        if (self::class == static::class) {
            throw new Exception('no plan to call generate, maybe you want to call generatePage ?');
        }
    }

    public function generatePage(Page $page): void
    {
        if (false !== $page->getRedirection()) {
            $this->redirectionManager->add($page);

            return;
        }

        $this->saveAsStatic($this->generateLivePathFor($page), $this->generateFilePath($page));

        $this->generateFeedFor($page);
    }

    protected function generateFilePath(Page $page)
    {
        $slug = '' == $page->getRealSlug() ? 'index' : $page->getRealSlug();

        if (pathinfo($page->getRealSlug(), PATHINFO_EXTENSION)) {
            return $this->getStaticDir().'/'.$slug;
        }

        return $this->getStaticDir().'/'.$slug.'.html';
    }

    /**
     * Generate static file for feed indexing children pages
     * (only if children pages exists).
     *
     * @return void
     */
    protected function generateFeedFor(Page $page)
    {
        $liveUri = $this->generateLivePathFor($page, 'pushword_page_feed');
        $staticFile = preg_replace('/.html$/', '.xml', $this->generateFilePath($page));
        if (! \is_array($page->getChildrenPages()) || ! \count($page->getChildrenPages())) {
            return;
        }

        $this->saveAsStatic($liveUri, $staticFile);
    }

    protected function saveAsStatic($liveUri, $destination)
    {
        $request = Request::create($liveUri);
        //$request->headers->set('host', $this->app->getMainHost());

        $response = static::$appKernel->handle($request);

        if ($response->isRedirect()) {
            if ($response->headers->get('location')) {
                $this->redirectionManager->add($liveUri, $response->headers->get('location'), $response->getStatusCode());
            }

            return;
        } elseif (200 != $response->getStatusCode()) {
            //$this->kernel = static::$appKernel;
            if (500 === $response->getStatusCode() && 'dev' == $this->kernel->getEnvironment()) {
                throw new Exception('An error occured when generating `'.$liveUri.'`'); //exit($this->kernel->handle($request));
            }

            return;
        }

        if (false !== strpos($response->headers->all()['content-type'][0] ?? '', 'html')) {
            $content = $this->compress($response->getContent());
        } else {
            $content = $response->getContent();
        }

        $this->filesystem->dumpFile($destination, $content);
    }

    protected function compress($html)
    {
        return $this->parser->compress($html);
    }
}
