<?php

namespace Pushword\AdvancedMainImage;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service pour récupérer la configuration des formats d'image principale.
 * Reconstruit le tableau à partir des paramètres imbriqués créés par PushwordConfigFactory.
 */
final class MainImageFormatsConfig
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function getFormats(): array
    {
        return self::getFormatsStatic($this->parameterBag);
    }

    /**
     * Méthode statique pour récupérer les formats sans injection de dépendances.
     *
     * @return array<string, int>
     */
    public static function getFormatsStatic(?ParameterBagInterface $parameterBag = null): array
    {
        if (null === $parameterBag) {
            global $kernel;
            if (! isset($kernel)) {
                throw new \LogicException('Kernel is not available. Cannot retrieve main image formats configuration.');
            }
            /** @var \Symfony\Component\HttpKernel\KernelInterface $kernel */
            $container = $kernel->getContainer();
            // @phpstan-ignore-next-line
            if (null === $container) {
                throw new \LogicException('Container is not available. Cannot retrieve main image formats configuration.');
            }
            /** @var ParameterBagInterface $parameterBag */
            // @phpstan-ignore-next-line
            $parameterBag = $container->getParameterBag();
        }

        $prefix = 'pw.pushword_advanced_main_image.main_image_formats.';
        $formats = [];

        /** @var array<string, mixed> $allParams */
        $allParams = $parameterBag->all();
        foreach ($allParams as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $formatKey = substr($key, \strlen($prefix));
                /** @var int|string|float|bool $value */
                $formats[$formatKey] = (int) $value;
            }
        }

        return $formats;
    }
}
