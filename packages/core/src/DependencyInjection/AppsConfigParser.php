<?php

namespace Pushword\Core\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AppsConfigParser
{
    public static function parse(array $apps, ContainerBuilder $container): array
    {
        $result = [];
        foreach ($apps as $app) {
            $app = self::parseAppConfig($app, $container);
            $result[$app['hosts'][0]] = $app;
        }

        return $result;
    }

    private static function parseAppConfig(array $app, ContainerBuilder $container): array
    {
        $properties = $container->getParameter('pw.app_fallback_properties');
        if (\is_string($properties)) {
            $properties = explode(',', $properties);
        }

        foreach ($properties as $p) {
            if (! isset($app[$p])) {
                $app[$p] = $container->getParameter('pw.'.$p); //'%'.'pw.'.$p.'%';
            } elseif ('custom_properties' == $p) {
                $app[$p] = array_merge($container->getParameter('pw.'.$p), $app[$p]);
                //var_dump($app[$p]); exit;
            }
        }

        return $app;
    }
}
