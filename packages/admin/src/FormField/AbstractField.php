<?php

namespace Pushword\Admin\FormField;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field as EasyField;
use InvalidArgumentException;
use Pushword\Admin\AdminFormFieldManager;
use Pushword\Admin\AdminInterface;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\PageRepository;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @template T of object
 */
abstract class AbstractField
{
    /**
     * @param AdminInterface<T> $admin
     */
    public function __construct(
        protected AdminFormFieldManager $formFieldManager,
        protected AdminInterface $admin
    ) {
    }

    /**
     * @return FieldInterface|iterable<FieldInterface>|null
     */
    public function getEasyAdminField(): FieldInterface|iterable|null
    {
        return null;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function buildEasyAdminField(string $property, ?string $formType = null, array $options = []): EasyField
    {
        $label = $options['label'] ?? null;
        unset($options['label']);

        if (null !== $label && ! \is_string($label) && false !== $label) {
            throw new InvalidArgumentException('Label must be a string, false or null.');
        }

        $field = EasyField::new($property, $label)->onlyOnForms();

        if (null !== $formType) {
            $field->setFormType($formType);
        }

        if ([] !== $options) {
            $field->setFormTypeOptions($options);
        }

        return $field;
    }

    // Alias
    // -----

    protected function entityManager(): EntityManagerInterface
    {
        return $this->formFieldManager->em;
    }

    protected function currentRequest(): Request
    {
        return $this->formFieldManager->requestStack->getCurrentRequest() ?? throw new RuntimeException('No current request found');
    }

    protected function pageRepo(): PageRepository
    {
        return $this->formFieldManager->pageRepo;
    }

    protected function mediaRepo(): MediaRepository
    {
        return $this->formFieldManager->mediaRepo;
    }
}
