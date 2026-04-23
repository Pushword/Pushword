<?php

namespace Pushword\StaticGenerator\Cache\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Admin\FormField\AbstractField;
use Pushword\Core\Entity\Page;
use Pushword\StaticGenerator\StaticAppGenerator;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

/**
 * @extends AbstractField<Page>
 */
final class PageCacheField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        $host = $this->admin->getSubject()->host;
        $app = '' !== $host
            ? $this->formFieldManager->apps->findByHost($host)
            : $this->formFieldManager->apps->getDefault();

        if (null === $app || ! StaticAppGenerator::isCacheMode($app)) {
            return null;
        }

        return $this->buildEasyAdminField('cache', CheckboxType::class, [
            'label' => 'adminPageCacheLabel',
            'help' => 'adminPageCacheHelp',
            'required' => false,
        ]);
    }
}
