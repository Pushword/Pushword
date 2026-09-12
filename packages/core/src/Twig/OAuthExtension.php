<?php

declare(strict_types=1);

namespace Pushword\Core\Twig;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Provides OAuth providers as a Twig global variable.
 * This extension is only registered when KnpU OAuth2 Client Bundle is installed.
 */
final class OAuthExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ?ClientRegistry $clientRegistry = null,
    ) {
    }

    /**
     * @return array<string, array<array{name: string, label: string}>>
     */
    public function getGlobals(): array
    {
        if (null === $this->clientRegistry) {
            return ['oauth_providers' => []];
        }

        /** @var string[] $clientKeys */
        $clientKeys = $this->clientRegistry->getEnabledClientKeys();

        return ['oauth_providers' => array_values(array_map(
            static fn (string $name): array => ['name' => $name, 'label' => ucfirst($name)],
            $clientKeys,
        ))];
    }
}
