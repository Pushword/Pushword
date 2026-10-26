<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Form\Extension;

use PHPUnit\Framework\TestCase;
use Pushword\Admin\Form\Extension\ContextHelpTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Forms;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\TranslatableMessage;

final class ContextHelpTypeExtensionTest extends TestCase
{
    public function testOnlyAFieldThatOptsInMovesItsHelpIntoTheReference(): void
    {
        $view = Forms::createFormFactoryBuilder()
            ->addTypeExtension(new ContextHelpTypeExtension(new IdentityTranslator()))
            ->getFormFactory()
            ->createNamedBuilder('site')
            ->add('reference', TextType::class, ['label' => 'Reference', 'help' => 'Long explanation', 'context_help' => true])
            ->add('summarized', TextType::class, ['label' => 'Summarized', 'help' => 'Long explanation', 'context_help' => 'Short line'])
            ->add('plain', TextType::class, ['label' => 'Title', 'help' => 'adminPageTitleHelp'])
            ->getForm()
            ->createView();

        self::assertSame(
            ['data-pw-help' => 'contextHelpFor', 'data-pw-help-id' => 'site_reference_reference_help'],
            $view['reference']->vars['row_attr'],
        );
        self::assertSame(
            ['data-pw-help' => 'contextHelpFor', 'data-pw-help-id' => 'site_summarized_reference_help', 'data-pw-help-summary' => 'Short line'],
            $view['summarized']->vars['row_attr'],
        );
        self::assertSame([], $view['plain']->vars['row_attr']);
    }

    public function testATranslatableLabelIsTranslatedAndALabellessFieldIsSkipped(): void
    {
        $view = Forms::createFormFactoryBuilder()
            ->addTypeExtension(new ContextHelpTypeExtension(new IdentityTranslator()))
            ->getFormFactory()
            ->createNamedBuilder('site')
            ->add('translatable', TextType::class, ['label' => new TranslatableMessage('Title'), 'context_help' => true])
            ->add('unlabelled', TextType::class, ['label' => false, 'context_help' => true])
            ->getForm()
            ->createView();

        self::assertSame(
            ['data-pw-help' => 'contextHelpFor', 'data-pw-help-id' => 'site_translatable_reference_help'],
            $view['translatable']->vars['row_attr'],
        );
        self::assertSame([], $view['unlabelled']->vars['row_attr']);
    }
}
