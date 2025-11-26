<?php

namespace Pushword\Admin\Twig;

use InvalidArgumentException;
use Pushword\Admin\Service\AdminUrlGeneratorAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Twig extension to support old Sonata-style routing in templates.
 * Intercepts admin_* route names and converts them to EasyAdmin URLs.
 */
class AdminRoutingExtension
{
    public function __construct(
        private readonly AdminUrlGeneratorAlias $adminUrlGenerator,
        #[Autowire(service: 'router')]
        private readonly UrlGeneratorInterface $router
    ) {
    }

    /**
     * Generate path (routes starting with admin_ use EasyAdmin).
     *
     * @param array<string, mixed> $parameters
     */
    #[AsTwigFunction('path')]
    public function generatePath(string $routeName, array $parameters = [], bool $relative = false): string
    {
        // If it's an admin route (old Sonata style), use our alias
        if (str_starts_with($routeName, 'admin_page_')
            || str_starts_with($routeName, 'admin_media_')
            || str_starts_with($routeName, 'admin_user_')) {
            try {
                return $this->adminUrlGenerator->generate($routeName, $parameters);
            } catch (InvalidArgumentException) {
                // Fall through to default router if route not found in alias
            }
        }

        // Otherwise use default Symfony routing
        return $this->router->generate(
            $routeName,
            $parameters,
            $relative ? UrlGeneratorInterface::RELATIVE_PATH : UrlGeneratorInterface::ABSOLUTE_PATH
        );
    }

    /**
     * Generate URL (absolute).
     *
     * @param array<string, mixed> $parameters
     */
    #[AsTwigFunction('url')]
    public function generateUrl(string $routeName, array $parameters = []): string
    {
        // Admin routes
        if (str_starts_with($routeName, 'admin_page_')
            || str_starts_with($routeName, 'admin_media_')
            || str_starts_with($routeName, 'admin_user_')) {
            try {
                return $this->adminUrlGenerator->generate($routeName, $parameters);
            } catch (InvalidArgumentException) {
                // Fall through to default router
            }
        }

        // Default Symfony routing
        return $this->router->generate($routeName, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
