<?php

declare(strict_types=1);

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\Page;

/**
 * Shared by the page association fields, whose candidate list depends on the page
 * being edited.
 */
trait PageFormSubjectTrait
{
    /**
     * The page the form is about.
     *
     * EasyAdmin rebuilds the fields against an empty subject when it answers an
     * autocomplete request, so there the page is the one
     * {@see AutocompleteSubjectConfigurator} named in the endpoint URL. A page being
     * created is named by nothing, and needs no name: the empty subject already carries
     * the host it will be created on, which is all its filters have left to read.
     */
    private function pageFormSubject(): Page
    {
        /** @var Page $subject */
        $subject = $this->admin->getSubject();

        if (null !== $subject->id) {
            return $subject;
        }

        $id = $this->admin->getRequest()?->query->getInt(AutocompleteSubjectConfigurator::SUBJECT_ID_PARAM) ?? 0;

        if (0 === $id) {
            return $subject;
        }

        return $this->pageRepo()->find($id) ?? $subject;
    }
}
