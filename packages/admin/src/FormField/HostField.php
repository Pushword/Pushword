<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use Pushword\Core\Entity\SharedTrait\HostInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @extends AbstractField<HostInterface>
 */
class HostField extends AbstractField
{
    private function getDefaultHost(): string
    {
        $request = $this->admin->getRequest();

        if ($request instanceof Request) {
            $host = $request->query->get('host');

            if (\is_string($host)) {
                $this->formFieldManager->apps->switchCurrentApp($host); // todo move it before fields initializations

                return $host;
            }

            if ($request->isMethod('POST')) {
                // POST request query parameter is not transmitted
                return '';
            }
        }

        return $this->getHosts()[0];
    }

    /**
     * @return string[]
     */
    private function getHosts(): array
    {
        return $this->formFieldManager->apps->getHosts();
    }

    public function getEasyAdminField(): ?FieldInterface
    {
        $hosts = $this->getHosts();

        if (1 === \count($hosts)) {
            $this->admin->getSubject()->host = $this->formFieldManager->apps->get()->getMainHost();

            return null;
        }

        if ('' === $this->admin->getSubject()->host) {
            $this->admin->getSubject()->host = $this->getDefaultHost();
        }

        return ChoiceField::new('host', 'adminPageHostLabel')
            ->onlyOnForms()
            ->setChoices(array_combine($hosts, $hosts))
            ->renderAsNativeWidget()
            ->setFormTypeOption('required', true);
    }
}
