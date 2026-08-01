<?php

namespace Pushword\Core\PropertySchema;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A bundle declares the page custom properties it reads. Implementations are
 * tagged `pushword.page_properties_provider` (autoconfigured) and merged into
 * every host's schema, below root and per-app `page_properties` config — a
 * site can tighten or un-declare (`name: ~`) what a bundle declared.
 */
#[AutoconfigureTag('pushword.page_properties_provider')]
interface PagePropertiesProviderInterface
{
    /**
     * Property name => descriptor, in the `page_properties` config shape
     * ({@see PagePropertySchemaFactory}).
     *
     * @return array<string, array<string, mixed>>
     */
    public function getPageProperties(): array;
}
