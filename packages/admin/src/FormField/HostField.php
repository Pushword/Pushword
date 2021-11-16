<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\SharedTrait\HostInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\DoctrineORMAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * @extends AbstractField<HostInterface>
 */
class HostField extends AbstractField
{
    /**
     * @param FormMapper<HostInterface> $form
     *
     * @return FormMapper<HostInterface>
     */
    public function formField(FormMapper $form): FormMapper
    {
        if (1 === \count($this->admin->getApps()->getHosts())) {
            $this->admin->getSubject()->setHost($this->admin->getApps()->get()->getMainHost());

            return $form;
        }

        if ('' === $this->admin->getSubject()->getHost()) {
            $this->admin->getSubject()->setHost($this->admin->getApps()->getMainHost());
        }

        return $form->add('host', ChoiceType::class, [
            'choices' => \Safe\array_combine($this->getHosts(), $this->getHosts()),
            'required' => false,
            'label' => 'admin.page.host.label',
            'empty_data' => $this->getHosts()[0],
        ]);
    }

    /**
     * @param DatagridMapper<HostInterface> $datagrid
     *
     * @return DatagridMapper<HostInterface>
     */
    public function datagridMapper(DatagridMapper $datagrid): DatagridMapper
    {
        return $datagrid->add('host', ChoiceFilter::class, [
            'field_type' => ChoiceType::class,
            'field_options' => [
                'choices' => \Safe\array_combine($this->getHosts(), $this->getHosts()),
                'multiple' => true,
            ],
            'label' => 'admin.page.host.label',
        ]);
    }

    /**
     * @return string[]
     */
    private function getHosts(): array
    {
        return $this->admin->getApps()->getHosts();
    }
}
