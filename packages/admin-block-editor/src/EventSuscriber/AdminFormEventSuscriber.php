<?php

namespace Pushword\AdminBlockEditor\EventSuscriber;

use Pushword\Admin\FormField\Event as FormEvent;
use Pushword\Admin\FormField\PageH1Field;
use Pushword\Admin\FormField\PageMainContentField;
use Pushword\Admin\PageAdminInterface;
use Pushword\AdminBlockEditor\PageH1FormField;
use Pushword\AdminBlockEditor\PageMainContentFormField;
use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Event\PersistenceEvent;

class AdminFormEventSuscriber extends AbstractEventSuscriber
{
    public static function getSubscribedEvents(): array
    {
        return [
            'pushword.admin.load_field' => 'replaceFields',
            'sonata.admin.event.persistence.pre_update' => 'setMainContent',
            'sonata.admin.event.persistence.pre_persist' => 'setMainContent',
        ];
    }

    public function setMainContent(PersistenceEvent $event): void
    {
        if (! $event->getAdmin() instanceof PageAdminInterface) {
            return;
        }

        $returnValues = $event->getAdmin()->getRequest()->get($event->getAdmin()->getRequest()->get('uniqid'));
        if ($returnValues['jsMainContent']) {
            // sanitize with https://github.com/editor-js/editorjs-php // todo
            $event->getAdmin()->getSubject()->setMainContent($returnValues['jsMainContent']);
        }
    }

    /** @psalm-suppress  NoInterfaceProperties */
    public function replaceFields(FormEvent $event): void
    {
        if (! $event->getAdmin() instanceof PageAdminInterface || ! $this->mayUseEditorBlock($event->getAdmin()->getSubject())) {
            return;
        }

        $fields = $event->getFields();

        $fields = $this->replace(PageMainContentField::class, PageMainContentFormField::class, $fields);
        $fields = $this->replace(PageH1Field::class, PageH1FormField::class, $fields);

        $event->setFields($fields);

        /** @var PageInterface $page */
        $page = $event->getAdmin()->getSubject();
        $page->jsMainContent = $this->transformMainContent($page->getMainContent());
    }

    private function transformMainContent($content)
    {
        $jsonContent = json_decode($content);
        if (false === $jsonContent) {
            // we just start to use editor.js for this page... try parsing raw content and creating a JS
            return '{}'; // todo
            // {"time":1611744796620,"blocks":[{"type":"paragraph","data":{"text":"test"}},{"type":"paragraph","data":{"text":"test"}}],"version":"2.19.1"}
        }

        return $content;
    }

    private function replace(string $formFieldClass, string $newFormFieldClass, $fields): array
    {
        $key = array_search($formFieldClass, $fields[0]);
        if (false !== $key) {
            $fields[0][$key] = $newFormFieldClass;
        }

        return $fields;
    }
}
