<?php

namespace Pushword\Core\PropertySchema;

/**
 * Custom properties the core bundle itself reads: the table of contents pair
 * consumed by SplitContent and the search excerpt read by templates and the
 * search extension.
 */
final class CorePagePropertiesProvider implements PagePropertiesProviderInterface
{
    public function getPageProperties(): array
    {
        return [
            'toc' => ['type' => PagePropertyType::Bool->value],
            'tocTitle' => ['type' => PagePropertyType::String->value],
            'searchExcerpt' => ['type' => PagePropertyType::String->value],
        ];
    }
}
