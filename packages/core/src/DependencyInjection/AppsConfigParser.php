<?php

namespace Pushword\Core\DependencyInjection;

use LogicException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AppsConfigParser
{
    /**
     * @param array<array<(string|int), array<mixed>>> $apps
     *
     * @return array<array<mixed>>
     */
    public static function parse(array $apps, ContainerBuilder $containerBuilder): array
    {
        $result = [];
        foreach ($apps as $app) {
            $app = self::parseAppConfig($app, $containerBuilder);
            $result[$app['hosts'][0]] = $app; // @phpstan-ignore-line
        }

        return $result;
    }

    /**
     * @param array<(string|int), array<mixed>> $app
     *
     * @return array<mixed>
     */
    private static function parseAppConfig(array $app, ContainerBuilder $containerBuilder): array
    {
        $properties = $containerBuilder->getParameter('pw.app_fallback_properties');
        if (\is_string($properties)) { // @phpstan-ignore-line
            $properties = explode(',', $properties);
        }

        foreach ($properties as $property) {
            if (! isset($app[$property])) {
                $app[$property] = $containerBuilder->getParameter('pw.'.$property); //'%'.'pw.'.$p.'%';
            } elseif ('custom_properties' == $property) {
                $app[$property] = array_merge(self::getParameterArray($containerBuilder, 'pw.'.$property), $app[$property]); // @phpstan-ignore-line
                //var_dump($app[$p]); exit;
            }
        }

        return $app;
    }

    /**
     * @return array<mixed>
     */
    private static function getParameterArray(ContainerBuilder $containerBuilder, string $parameterName): array
    {
        $return = $containerBuilder->getParameter($parameterName);
        if (! \is_array($return)) {
            throw new LogicException('Parameter '.$parameterName.' must be an array');
        }

        return $return;
    }
}
