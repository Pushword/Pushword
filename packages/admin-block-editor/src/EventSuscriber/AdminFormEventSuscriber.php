<?php

namespace Pushword\AdminBlockEditor\EventSuscriber;

use Pushword\Admin\FormField\Event as FormEvent;
use Pushword\Admin\FormField\PageH1Field;
use Pushword\Admin\FormField\PageMainContentField;
use Pushword\Admin\PageAdmin;
use Pushword\Admin\Utils\FormFieldReplacer;
use Pushword\AdminBlockEditor\FormField\PageH1FormField;
use Pushword\AdminBlockEditor\FormField\PageImageFormField;
use Pushword\AdminBlockEditor\FormField\PageMainContentFormField;
use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Event\PersistenceEvent;

/**
 * @template T of object
 */
class AdminFormEventSuscriber extends AbstractEventSuscriber
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'pushword.admin.load_field' => 'replaceFields',
            'sonata.admin.event.persistence.pre_update' => 'setMainContent',
            'sonata.admin.event.persistence.pre_persist' => 'setMainContent',
        ];
    }

    /**
     * @param PersistenceEvent<PageInterface> $persistenceEvent
     */
    public function setMainContent(PersistenceEvent $persistenceEvent): void
    {
        if (! $persistenceEvent->getAdmin() instanceof PageAdmin) {
            return;
        }

        $requestUniqId = (string) $persistenceEvent->getAdmin()->getRequest()->query->get('uniqid');
        $returnValues = $persistenceEvent->getAdmin()->getRequest()->request->all($requestUniqId);
        if (! isset($returnValues['mainContent'])) {
            return;
        }

        if (! \is_string($returnValues['mainContent'])) {
            return;
        }

        // sanitize with https://github.com/editor-js/editorjs-php
        $persistenceEvent->getAdmin()->getSubject()->setMainContent($returnValues['mainContent']);
    }

    /**
     * @psalm-suppress  NoInterfaceProperties
     *
     * @param FormEvent<T> $formEvent
     */
    public function replaceFields(FormEvent $formEvent): void
    {
        if (! $formEvent->getAdmin() instanceof PageAdmin) {
            return;
        }

        if (! $this->mayUseEditorBlock($formEvent->getAdmin()->getSubject())) {
            return;
        }

        $fields = $formEvent->getFields();

        $fields = (new FormFieldReplacer())->run(PageMainContentField::class, PageMainContentFormField::class, $fields);
        $fields = (new FormFieldReplacer())->run(PageH1Field::class, PageH1FormField::class, $fields);

        if (! \is_array($fields[0])) {
            throw new \LogicException();
        }

        $fields[0][PageImageFormField::class] = PageImageFormField::class;

        $formEvent->setFields($fields);
    }
}
