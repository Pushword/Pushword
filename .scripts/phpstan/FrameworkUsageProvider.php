<?php

declare(strict_types=1);

namespace Pushword\PHPStan;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Override;
use ReflectionMethod;
use ReflectionProperty;

use function Safe\simplexml_load_file;

use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;

final class FrameworkUsageProvider extends ReflectionBasedMemberUsageProvider
{
    /** @var array<string, list<array{name: string, event: string, method: string}>> */
    private array $listeners = [];

    public function __construct(string $containerXmlPath)
    {
        $container = simplexml_load_file($containerXmlPath);
        foreach ($container->services->service as $service) {
            foreach ($service->tag as $tag) {
                $name = (string) $tag['name'];
                if (! \in_array($name, ['kernel.event_listener', 'doctrine.event_listener', 'doctrine.orm.entity_listener'], true)) {
                    continue;
                }

                $this->listeners[(string) $service['class']][] = [
                    'name' => $name,
                    'event' => (string) $tag['event'],
                    'method' => (string) $tag['method'],
                ];
            }
        }
    }

    #[Override]
    protected function shouldMarkMethodAsUsed(ReflectionMethod $method): ?VirtualUsageData
    {
        if (! $method->isPublic()) {
            return null;
        }

        if ([] !== $method->getAttributes(AdminRoute::class)) {
            return VirtualUsageData::withNote('EasyAdmin route');
        }

        $class = $method->getDeclaringClass();
        foreach ($this->listeners[$class->getName()] ?? [] as $tag) {
            $listenerMethod = $tag['method'];
            if ('' === $listenerMethod) {
                $listenerMethod = 'kernel.event_listener' === $tag['name']
                    ? 'on'.str_replace(['.', '_'], '', ucwords($tag['event'], '._'))
                    : $tag['event'];
                if (! $class->hasMethod($listenerMethod)) {
                    $listenerMethod = '__invoke';
                }
            }

            if ($listenerMethod === $method->getName()) {
                return VirtualUsageData::withNote('Service listener tag');
            }
        }

        return null;
    }

    #[Override]
    protected function shouldMarkPropertyAsRead(ReflectionProperty $property): ?VirtualUsageData
    {
        if ($property->getDeclaringClass()->isTrait() && str_contains($property->getDocComment() ?: '', '@api')) {
            return VirtualUsageData::withNote('Trait property contract');
        }

        return null;
    }

    #[Override]
    protected function shouldMarkPropertyAsWritten(ReflectionProperty $property): ?VirtualUsageData
    {
        return $this->shouldMarkPropertyAsRead($property);
    }
}
