<?php

namespace Pushword\Admin\FormField;

use DateTime;
use DateTimeInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormInterface;

/**
 * @extends AbstractField<Page>
 */
class PagePublishedAtField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('publishedAt', DateTimeType::class, [
            'widget' => 'single_text',
            'with_seconds' => false,
            'html5' => true,
            'setter' => static function (?object &$viewData, mixed $submittedValue, FormInterface $form): void {
                if (! $viewData instanceof Page) {
                    return;
                }

                if (self::isSameMinute($viewData->publishedAt, $submittedValue)) {
                    return;
                }

                $viewData->publishedAt = $submittedValue instanceof DateTimeInterface ? $submittedValue : null;
            },
            'label' => 'adminPagePublishedAtLabel',
            'help' => $this->getHelp(),
            'help_html' => true,
        ]);
    }

    /**
     * The widget holds minutes, every write path stores seconds ({@see \Pushword\Admin\Controller\PageCrudController::togglePublished()},
     * flat import). A submitted value landing on the stored minute is therefore the
     * untouched form coming back, not an edit: keep the stored value rather than
     * truncate it, so re-saving a page leaves the entity clean instead of shifting
     * its publication date back by up to 59 seconds and cutting a version for it.
     */
    private static function isSameMinute(mixed $stored, mixed $submitted): bool
    {
        return $stored instanceof DateTimeInterface
            && $submitted instanceof DateTimeInterface
            && $stored->format('Y-m-d H:i') === $submitted->format('Y-m-d H:i');
    }

    private function getHelp(): string
    {
        $page = $this->getSubject();
        $publishedAt = $page->publishedAt;
        $draft = null === $publishedAt || $publishedAt > new DateTime('now');

        return $this->formFieldManager->twig->render('@pwAdmin/page/page_draft.html.twig', ['page' => $page, 'draft' => $draft]);
    }

    private function getSubject(): Page
    {
        return $this->admin->getSubject();
    }
}
