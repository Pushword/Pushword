<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\SharedTrait\HostInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\DoctrineORMAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

/**
 * @extends AbstractField<HostInterface>
 */
class HostField extends AbstractField
{
    /**
     * @param FormMapper<HostInterface> $form
     */
    public function formField(FormMapper $form): void
    {
        if (1 === \count($this->formFieldManager->apps->getHosts())) {
            $this->admin->getSubject()->setHost($this->formFieldManager->apps->get()->getMainHost());

            return;
        }

        if ('' === $this->admin->getSubject()->getHost()) {
            $this->admin->getSubject()->setHost($this->getDefaultHost());
        }

        $form->add('host', HiddenType::class);
    }

    private function getDefaultHost(): string
    {
        if ($this->admin->hasRequest() && ($host = $this->admin->getRequest()->query->get('host')) !== null) {
            $this->formFieldManager->apps->switchCurrentApp($host); // todo move it before fields initializations

            return $host;
        }

        return $this->getHosts()[0];
    }

    /**
     * @param DatagridMapper<HostInterface> $datagrid
     */
    public function datagridMapper(DatagridMapper $datagrid): void
    {
        $datagrid->add('host', ChoiceFilter::class, [
            'field_type' => ChoiceType::class,
            'field_options' => [
                'choices' => array_combine($this->getHosts(), $this->getHosts()),
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
        return $this->formFieldManager->apps->getHosts();
    }
}
