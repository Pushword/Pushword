<?php

namespace Pushword\Core\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class PageRendering extends Constraint
{
    //public $message = 'The page is not rendering as expected... You may done an error in the main content.';
    public $message = 'page.pageRendering';

    public function validatedBy()
    {
        return 'page_rendering';
    }

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
