<?php

namespace Pushword\Core\Validator\Constraints;

use Override;
use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class PageRendering extends Constraint
{
    // public $message = 'The page is not rendering as expected... You may done an error in the main content.';
    public string $message = 'page.pageRendering';

    #[Override]
    public function validatedBy(): string
    {
        return 'page_rendering';
    }

    #[Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
