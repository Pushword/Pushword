<?php

namespace Pushword\StaticGenerator\Generator;

class RobotsGenerator extends PageGenerator
{
    public function generate(?string $host = null): void
    {
        parent::generate($host);

        foreach ($this->app->getLocales() as $locale) {
            foreach (['txt', 'xml'] as $format) {
                $this->generateSitemap($locale, $format);
            }

            $this->generateFeed($locale);
            $this->generateRobotsTxt($locale);
        }
    }

    protected function generateSitemap($locale, $format)
    {
        $liveUri = $this->generateLivePathFor(
            $this->app->getMainHost(),
            'pushword_page_sitemap',
            ['locale' => $locale, '_format' => $format]
        );

        if ($this->app->getLocale() == $locale) {
            $staticFile = $this->getStaticDir().'/sitemap.'.$format;
        } else {
            $staticFile = $this->getStaticDir().'/'.$locale.'/sitemap.'.$format;
        }

        $this->saveAsStatic($liveUri, $staticFile);
    }

    protected function generateFeed($locale)
    {
        if (! $this->getPageRepository()->getPage('homepage', $this->app->getMainHost())) {
            return;
            // we can't generate main feed if no homepage exist
            // because mainFeed rely on homepage data
        }

        $liveUri = $this->generateLivePathFor(
            $this->app->getMainHost(),
            'pushword_page_main_feed',
            ['locale' => $locale]
        );

        if ($this->app->get('locale') == $locale) {
            $staticFile = $this->getStaticDir().'/feed.xml';
        } else {
            $staticFile = $this->getStaticDir().'/'.$locale.'/feed.xml';
        }

        $this->saveAsStatic($liveUri, $staticFile);
    }

    protected function generateRobotsTxt($locale)
    {
        $liveUri = $this->generateLivePathFor(
            $this->app->getMainHost(),
            'pushword_page_robots_txt',
            ['locale' => $locale]
        );

        if ($this->app->get('locale') == $locale) {
            $staticFile = $this->getStaticDir().'/robots.txt';
        } else {
            $staticFile = $this->getStaticDir().'/'.$locale.'/robots.txt';
        }

        $this->saveAsStatic($liveUri, $staticFile);
    }
}
