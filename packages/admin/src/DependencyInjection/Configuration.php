<?php

namespace Pushword\Admin\DependencyInjection;

use Pushword\Admin\FormField\CreatedAtField;
use Pushword\Admin\FormField\CustomPropertiesField;
use Pushword\Admin\FormField\HostField;
use Pushword\Admin\FormField\MediaMediaFileField;
use Pushword\Admin\FormField\MediaNameField;
use Pushword\Admin\FormField\MediaNamesField;
use Pushword\Admin\FormField\MediaSlugField;
use Pushword\Admin\FormField\PageEditMessageField;
use Pushword\Admin\FormField\PageH1Field;
use Pushword\Admin\FormField\PageLocaleField;
use Pushword\Admin\FormField\PageMainContentField;
use Pushword\Admin\FormField\PageMainImageField;
use Pushword\Admin\FormField\PageMetaRobotsField;
use Pushword\Admin\FormField\PageNameField;
use Pushword\Admin\FormField\PageParentPageField;
use Pushword\Admin\FormField\PagePublishedAtField;
use Pushword\Admin\FormField\PageSearchExcreptField;
use Pushword\Admin\FormField\PageSlugField;
use Pushword\Admin\FormField\PageTitleField;
use Pushword\Admin\FormField\PageTranslationsField;
use Pushword\Admin\FormField\TagsField;
use Pushword\Admin\FormField\UserEmailField;
use Pushword\Admin\FormField\UserLocaleField;
use Pushword\Admin\FormField\UserPasswordField;
use Pushword\Admin\FormField\UserRolesField;
use Pushword\Admin\FormField\UserUsernameField;
use Pushword\Admin\FormField\WeightField;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    final public const array DEFAULT_APP_FALLBACK = [
        'admin_page_form_fields',
        'admin_redirection_form_fields',
        'admin_user_form_fields',
        'admin_media_form_fields',
    ];

    /**
     * Must respect AdminFormFields type define in ./phpstan.neon.dist !
     */
    final public const array DEFAULT_ADMIN_USER_FORM_FIELDS = [
        [UserEmailField::class, UserUsernameField::class, UserPasswordField::class, CreatedAtField::class],
        ['admin.user.label.security' => [UserRolesField::class, UserLocaleField::class]],
    ];

    final public const array DEFAULT_ADMIN_REDIRECTION_FORM_FIELDS = [
        [PageH1Field::class, PageMainContentField::class],
        [
            'admin.page.permanlien.label' => [
                HostField::class,
                PageSlugField::class],
            'admin.page.customProperties.label' => [
                'expand' => true,
                'fields' => [CustomPropertiesField::class],
            ],
        ],
    ];

    final public const array DEFAULT_ADMIN_PAGE_FORM_FIELDS = [
        [TagsField::class, PageH1Field::class, PageMainContentField::class],
        [
            'admin.page.revisions' => [PageEditMessageField::class],
            'admin.page.state.label' => [PagePublishedAtField::class, PageMetaRobotsField::class],
            'admin.page.permanlien.label' => [HostField::class, PageSlugField::class],
            'admin.page.mainImage.label' => [PageMainImageField::class],
            'admin.page.parentPage.label' => [PageParentPageField::class],
            'admin.page.search.label' => [
                'expand' => true,
                'fields' => [PageTitleField::class, PageNameField::class, PageSearchExcreptField::class, WeightField::class],
            ],
            'admin.page.translations.label' => [PageLocaleField::class, PageTranslationsField::class],
            'admin.page.customProperties.label' => [
                'expand' => true,
                'fields' => [CustomPropertiesField::class],
            ],
            /*
            'admin.page.og.label' => [
                'expand' => true,
                'fields' => [OgTitleField::class, OgDescriptionField::class, OgImageField::class,
             ],
            ],
            */
        ],
    ];

    final public const array DEFAULT_ADMIN_MEDIA_FORM_FIELDS = [
        [
            MediaMediaFileField::class,
            MediaNameField::class,
            TagsField::class,
            MediaSlugField::class,
        ],
        [CustomPropertiesField::class, MediaNamesField::class],
        [],
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pushword_admin');
        $treeBuilder
            ->getRootNode()
                ->children()
                    ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
                    ->variableNode('admin_page_form_fields')->defaultValue(self::DEFAULT_ADMIN_PAGE_FORM_FIELDS)->cannotBeEmpty()->end()
                    ->variableNode('admin_redirection_form_fields')->defaultValue(self::DEFAULT_ADMIN_REDIRECTION_FORM_FIELDS)->cannotBeEmpty()->end()
                    ->variableNode('admin_user_form_fields')->defaultValue(self::DEFAULT_ADMIN_USER_FORM_FIELDS)->cannotBeEmpty()->end()
                    ->variableNode('admin_media_form_fields')->defaultValue(self::DEFAULT_ADMIN_MEDIA_FORM_FIELDS)->cannotBeEmpty()->end()
                ->end()
        ;

        return $treeBuilder;
    }
}
