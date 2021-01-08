<?php

namespace Pushword\StaticGenerator\Generator;

class RobotsGenerator extends PageGenerator
{
    public function generate(?string $host = null): void
    {
        parent::generate($host);

        foreach ($this->app->getLocales() as $locale) { // todo, find locale by my self via repo
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
        } // todo get it from URI removing host

        $this->saveAsStatic($liveUri, $staticFile);
    }

    protected function generateFeed($locale)
    {
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
