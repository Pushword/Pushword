<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Pushword\Version\PushwordVersionBundle;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @extends AbstractField<Page>
 */
class PageEditMessageField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('editMessage', TextareaType::class, [
            'required' => false,
            'attr' => ['class' => 'autosize textarea-no-newline'],
            'label' => 'adminPageEditMessageLabel',
            'help' => $this->getHelp(),
            'help_html' => true,
        ]);
    }

    private function getHelp(): string
    {
        $page = $this->admin->getSubject();
        $editMessage = $page->editMessage;
        $page->editMessage = '';

        return null !== $page->id ?
            $this->admin->getTranslator()->trans('adminPageEditMessageHelp'.(class_exists(PushwordVersionBundle::class) ? 'Versionned' : ''), [
                '%lastEditDatetime%' => $page->updatedAt->format($this->admin->getTranslator()->trans('datetimeMediumFormat')), // @phpstan-ignore method.nonObject (property hook guarantees non-null)
                '%lastEditMessage%' => '' !== $editMessage ? '«&nbsp;'.$editMessage.'&nbsp;»' : '-',
                '%seeVersionLink%' => class_exists(PushwordVersionBundle::class)
                    ? $this->formFieldManager->router->generate('pushword_version_list', ['id' => $page->id])
                    : '',
            ]) : '';
    }

    private function getSubject(): Page
    {
        return $this->admin->getSubject();
    }
}
