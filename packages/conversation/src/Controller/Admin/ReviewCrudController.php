<?php

namespace Pushword\Conversation\Controller\Admin;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use function is_numeric;
use function mb_strlen;
use function mb_substr;

use Override;
use Pushword\Admin\Form\Type\MediaPickerType;
use Pushword\Conversation\Entity\Review;

use function sprintf;
use function str_repeat;

final class ReviewCrudController extends ConversationCrudController
{
    #[Override]
    public static function getEntityFqcn(): string
    {
        return Review::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('admin.label.review')
            ->setEntityLabelInPlural('admin.label.review')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->addFormTheme('@PushwordConversation/admin/review_form_theme.html.twig');
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            return $this->getIndexFields();
        }

        $this->registerRatingCustomProperty();

        return $this->getFormFieldsDefinition();
    }

    #[Override]
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $queryBuilder
            ->andWhere('entity.customProperties LIKE :ratingFilter')
            ->setParameter('ratingFilter', '%"rating":%');

        return $queryBuilder;
    }

    #[Override]
    protected function getMainFields(): array
    {
        $fields = [];
        $fields[] = $this->getTitleField();
        $fields[] = $this->getRatingFormField();
        $fields = array_merge($fields, parent::getMainFields());
        $fields[] = $this->getMediaPickerField();

        return $fields;
    }

    private function getRatingDisplayField(): IntegerField
    {
        return IntegerField::new('rating', 'admin.review.rating.label')
            ->setSortable(false)
            ->formatValue(static fn (?int $value): string => self::formatRating($value ?? 0));
    }

    private function getMediaPickerField(): CollectionField
    {
        $field = CollectionField::new('mediaList', 'admin.review.medias.label')
            ->onlyOnForms()
            ->setEntryType(MediaPickerType::class)
            ->setFormTypeOption('entry_options', [
                'label' => false,
                'media_picker_filters' => [
                    'mimeType' => [
                        'value' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                    ],
                ],
            ])
            ->setFormTypeOption('allow_add', true)
            ->setFormTypeOption('allow_delete', true)
            ->setFormTypeOption('by_reference', false)
            ->setFormTypeOption('prototype', true)
            ->setHelp('admin.review.medias.help');

        return $field;
    }

    private function getTitleField(): TextField
    {
        return TextField::new('title', 'admin.review.title.label')
            ->setHelp('admin.review.title.help')
            ->setColumns(12);
    }

    #[Override]
    protected function getIndexFields(): iterable
    {
        yield DateTimeField::new('publishedAt', 'admin.conversation.label.publishedAt')
            ->setSortable(true)
            ->setTemplatePath('@pwAdmin/components/published_toggle.html.twig');

        yield TextField::new('title', 'admin.review.title.label')
            ->setSortable(false)
            ->formatValue(fn (?string $value, ?Review $review): string => $this->formatTitleColumn($value, $review));

        yield $this->getRatingDisplayField();

        yield TextField::new('authorName', 'admin.conversation.authorName.label')
            ->setSortable(false);
        yield TextField::new('authorEmail', 'admin.conversation.authorEmail.label')
            ->setSortable(false);
        yield TextField::new('referring', 'admin.conversation.referring.label')
            ->setSortable(false);
        yield TextField::new('tags', 'admin.conversation.tags.label')
            ->setSortable(false)
            ->formatValue(static function (mixed $value, mixed $entity): string {
                if (! \is_object($entity) || ! method_exists($entity, 'getTags')) {
                    return '';
                }

                $tags = $entity->getTags();

                return \is_string($tags) ? $tags : '';
            });

        yield DateTimeField::new('createdAt', 'admin.conversation.createdAt.label')
            ->setSortable(true);
    }

    private function formatTitleColumn(?string $title, ?Review $review): string
    {
        $title = trim((string) $title);

        if ('' !== $title) {
            return $title;
        }

        if (! $review instanceof Review) {
            return '';
        }

        $content = strip_tags($review->getContent());
        $content = trim($content);

        if ('' === $content) {
            return '';
        }

        return $this->truncateText($content);
    }

    private function truncateText(string $text, int $limit = 90): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit - 1)).'…';
    }

    private function getRatingFormField(): ChoiceField
    {
        return ChoiceField::new('rating', 'admin.review.rating.label')
            ->setChoices($this->buildRatingChoices())
            ->renderExpanded()
            ->setHelp('admin.review.rating.help')
            ->setFormTypeOption('required', true)
            ->setFormTypeOption('row_attr', [
                'class' => 'pw-rating-field-row',
            ])
            ->setFormTypeOption('attr', [
                'class' => 'pw-rating-field__choices',
                'data-rating-widget' => 'true',
            ])
            ->setFormTypeOption('choice_label', static fn (mixed $choice, string $key, mixed $value): string => sprintf('%d / 5', (int) (is_numeric($value) ? $value : 0)))
            ->setFormTypeOption('choice_translation_domain', false);
    }

    /**
     * @return array<string, int>
     */
    private function buildRatingChoices(): array
    {
        $choices = [];

        foreach (\range(1, 5) as $value) {
            $choices[str_repeat('★', $value)] = $value;
        }

        return $choices;
    }

    private static function formatRating(int $value): string
    {
        if ($value < 1) {
            return '—';
        }

        $value = min($value, 5);

        return str_repeat('★', $value).str_repeat('☆', 5 - $value).' '.$value.'/5';
    }

    private function registerRatingCustomProperty(): void
    {
        $message = $this->getContext()?->getEntity()?->getInstance();

        if (! $message instanceof Review) {
            return;
        }

        $message->registerCustomPropertyField('rating');
    }
}
