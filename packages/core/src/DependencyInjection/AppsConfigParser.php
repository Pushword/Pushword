<?php

namespace Pushword\Core\DependencyInjection;

use Exception;
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
            $key = \is_string($key = $app['hosts'][0] ?? null) ? $key : throw new Exception(); // @phpstan-ignore-line
            $result[$key] = $app;
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
        /** @var string|array<string> */
        $properties = $containerBuilder->getParameter('pw.app_fallback_properties');
        if (\is_string($properties)) {
            $properties = explode(',', $properties);
        }

        foreach ($properties as $property) {
            if (! isset($app[$property])) {
                $app[$property] = $containerBuilder->getParameter('pw.'.$property); // '%'.'pw.'.$p.'%';
            } elseif ('custom_properties' == $property) {
                if (! \is_array($app['custom_properties'])) {
                    throw new Exception();
                }

                $app['custom_properties'] = array_merge(self::getParameterArray($containerBuilder, 'pw.'.$property), $app['custom_properties']);
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
