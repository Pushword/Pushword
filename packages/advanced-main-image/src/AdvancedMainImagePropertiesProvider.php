<?php

namespace Pushword\AdvancedMainImage;

use Pushword\Core\PropertySchema\PagePropertiesProviderInterface;
use Pushword\Core\PropertySchema\PagePropertyType;

/**
 * The stored format is the int the converter resolves labels to. Declaring it
 * keeps the key out of the undeclared-property reports; the dedicated
 * ChoiceField (registered managed before the generated fields build) stays
 * the editing surface.
 */
final class AdvancedMainImagePropertiesProvider implements PagePropertiesProviderInterface
{
    public function getPageProperties(): array
    {
        return [
            'mainImageFormat' => ['type' => PagePropertyType::Int->value],
        ];
    }
}
