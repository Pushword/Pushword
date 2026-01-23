<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Form\Type;

use PHPUnit\Framework\TestCase;
use Pushword\Admin\Form\Type\ApiTokenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ApiTokenTypeTest extends TestCase
{
    private ApiTokenType $formType;

    protected function setUp(): void
    {
        $this->formType = new ApiTokenType();
    }

    public function testGetParentReturnsTextType(): void
    {
        self::assertSame(TextType::class, $this->formType->getParent());
    }

    public function testGetBlockPrefixReturnsApiToken(): void
    {
        self::assertSame('api_token', $this->formType->getBlockPrefix());
    }

    public function testBuildViewSetsReadonlyAttribute(): void
    {
        $view = new FormView();
        $view->vars['attr'] = [];

        $form = $this->createStub(FormInterface::class);

        $options = [
            'placeholder' => 'Enter token...',
        ];

        $this->formType->buildView($view, $form, $options);

        self::assertArrayHasKey('attr', $view->vars);
        self::assertIsArray($view->vars['attr']);
        /** @var array<string, mixed> $attr */
        $attr = $view->vars['attr'];
        self::assertTrue($attr['readonly']);
        self::assertSame('form-control font-monospace api-token-input', $attr['class']);
        self::assertSame('Enter token...', $attr['placeholder']);
        self::assertSame('1', $attr['data-api-token-field']);
    }

    public function testBuildViewMergesExistingAttributes(): void
    {
        $view = new FormView();
        $view->vars['attr'] = [
            'id' => 'custom-id',
            'data-custom' => 'value',
        ];

        $form = $this->createStub(FormInterface::class);

        $options = [
            'placeholder' => '',
        ];

        $this->formType->buildView($view, $form, $options);

        self::assertIsArray($view->vars['attr']);
        /** @var array<string, mixed> $attr */
        $attr = $view->vars['attr'];
        self::assertSame('custom-id', $attr['id']);
        self::assertSame('value', $attr['data-custom']);
        self::assertTrue($attr['readonly']);
    }

    public function testConfigureOptionsSetsRequiredFalse(): void
    {
        $resolver = new OptionsResolver();

        $this->formType->configureOptions($resolver);

        $resolvedOptions = $resolver->resolve([]);

        self::assertFalse($resolvedOptions['required']);
        self::assertSame('', $resolvedOptions['placeholder']);
    }

    public function testConfigureOptionsAllowsCustomPlaceholder(): void
    {
        $resolver = new OptionsResolver();

        $this->formType->configureOptions($resolver);

        $resolvedOptions = $resolver->resolve([
            'placeholder' => 'Custom placeholder',
        ]);

        self::assertSame('Custom placeholder', $resolvedOptions['placeholder']);
    }

    public function testConfigureOptionsAllowsOverridingRequired(): void
    {
        $resolver = new OptionsResolver();

        $this->formType->configureOptions($resolver);

        $resolvedOptions = $resolver->resolve([
            'required' => true,
        ]);

        self::assertTrue($resolvedOptions['required']);
    }
}
