<?php

namespace Pushword\Core\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class PageRendering extends Constraint
{
    //public $message = 'The page is not rendering as expected... You may done an error in the main content.';
    public string $message = 'page.pageRendering';

    public function validatedBy(): string
    {
        return 'page_rendering';
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
