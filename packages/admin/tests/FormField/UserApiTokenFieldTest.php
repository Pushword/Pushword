<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Pushword\Admin\AdminFormFieldManager;
use Pushword\Admin\AdminInterface;
use Pushword\Admin\Form\Type\ApiTokenType;
use Pushword\Admin\FormField\UserApiTokenField;
use Pushword\Core\Entity\User;

final class UserApiTokenFieldTest extends TestCase
{
    /** @var AdminFormFieldManager&Stub */
    private Stub $formFieldManager;

    /** @var AdminInterface<User>&Stub */
    private Stub $admin;

    protected function setUp(): void
    {
        $this->formFieldManager = self::createStub(AdminFormFieldManager::class);
        $this->admin = self::createStub(AdminInterface::class);
    }

    private function createField(): UserApiTokenField
    {
        return new UserApiTokenField($this->formFieldManager, $this->admin);
    }

    private function createUser(?string $apiToken = null): User
    {
        $user = new User();
        $user->email = 'test@example.com';
        $user->apiToken = $apiToken;

        return $user;
    }

    public function testGetEasyAdminFieldReturnsFieldInterface(): void
    {
        $user = $this->createUser('test-token-123');
        $this->admin->method('getSubject')->willReturn($user);

        $field = $this->createField();
        $result = $field->getEasyAdminField();

        self::assertInstanceOf(FieldInterface::class, $result);
    }

    public function testFieldUsesApiTokenFormType(): void
    {
        $user = $this->createUser('test-token-123');
        $this->admin->method('getSubject')->willReturn($user);

        $field = $this->createField();
        $result = $field->getEasyAdminField();

        self::assertInstanceOf(FieldInterface::class, $result);

        $dto = $result->getAsDto();
        self::assertSame(ApiTokenType::class, $dto->getFormType());
    }

    public function testFieldIsOnlyOnForms(): void
    {
        $user = $this->createUser('test-token');
        $this->admin->method('getSubject')->willReturn($user);

        $field = $this->createField();
        $result = $field->getEasyAdminField();

        self::assertInstanceOf(FieldInterface::class, $result);

        $dto = $result->getAsDto();
        self::assertFalse($dto->isDisplayedOn('index'));
        self::assertFalse($dto->isDisplayedOn('detail'));
        self::assertTrue($dto->isDisplayedOn('edit'));
        self::assertTrue($dto->isDisplayedOn('new'));
    }

    public function testFieldSetsHasTokenDataAttributeWhenTokenExists(): void
    {
        $user = $this->createUser('existing-token-456');
        $this->admin->method('getSubject')->willReturn($user);

        $field = $this->createField();
        $result = $field->getEasyAdminField();

        self::assertInstanceOf(FieldInterface::class, $result);

        $dto = $result->getAsDto();
        $formTypeOptions = $dto->getFormTypeOptions();

        self::assertArrayHasKey('attr', $formTypeOptions);
        self::assertIsArray($formTypeOptions['attr']);
        /** @var array<string, mixed> $attr */
        $attr = $formTypeOptions['attr'];
        self::assertArrayHasKey('data-has-token', $attr);
        self::assertSame('1', $attr['data-has-token']);
    }

    public function testFieldSetsHasTokenDataAttributeWhenNoToken(): void
    {
        $user = $this->createUser(null);
        $this->admin->method('getSubject')->willReturn($user);

        $field = $this->createField();
        $result = $field->getEasyAdminField();

        self::assertInstanceOf(FieldInterface::class, $result);

        $dto = $result->getAsDto();
        $formTypeOptions = $dto->getFormTypeOptions();

        self::assertArrayHasKey('attr', $formTypeOptions);
        self::assertIsArray($formTypeOptions['attr']);
        /** @var array<string, mixed> $attr */
        $attr = $formTypeOptions['attr'];
        self::assertArrayHasKey('data-has-token', $attr);
        self::assertSame('0', $attr['data-has-token']);
    }

    public function testFieldSetsHasTokenDataAttributeWhenEmptyToken(): void
    {
        $user = $this->createUser('');
        $this->admin->method('getSubject')->willReturn($user);

        $field = $this->createField();
        $result = $field->getEasyAdminField();

        self::assertInstanceOf(FieldInterface::class, $result);

        $dto = $result->getAsDto();
        $formTypeOptions = $dto->getFormTypeOptions();

        self::assertIsArray($formTypeOptions['attr']);
        /** @var array<string, mixed> $attr */
        $attr = $formTypeOptions['attr'];
        self::assertSame('0', $attr['data-has-token']);
    }

    public function testFieldUsesCorrectPropertyName(): void
    {
        $user = $this->createUser('test-token');
        $this->admin->method('getSubject')->willReturn($user);

        $field = $this->createField();
        $result = $field->getEasyAdminField();

        self::assertInstanceOf(FieldInterface::class, $result);

        $dto = $result->getAsDto();
        self::assertSame('apiToken', $dto->getProperty());
    }

    public function testFieldHasCorrectLabel(): void
    {
        $user = $this->createUser('test-token');
        $this->admin->method('getSubject')->willReturn($user);

        $field = $this->createField();
        $result = $field->getEasyAdminField();

        self::assertInstanceOf(FieldInterface::class, $result);

        $dto = $result->getAsDto();
        self::assertSame('adminUserApiTokenLabel', $dto->getLabel());
    }

    public function testFieldHasHelpText(): void
    {
        $user = $this->createUser('test-token');
        $this->admin->method('getSubject')->willReturn($user);

        $field = $this->createField();
        $result = $field->getEasyAdminField();

        self::assertInstanceOf(FieldInterface::class, $result);

        $dto = $result->getAsDto();
        self::assertSame('adminUserApiTokenHelp', $dto->getHelp());
    }

    public function testFieldIsNotRequired(): void
    {
        $user = $this->createUser('test-token');
        $this->admin->method('getSubject')->willReturn($user);

        $field = $this->createField();
        $result = $field->getEasyAdminField();

        self::assertInstanceOf(FieldInterface::class, $result);

        $dto = $result->getAsDto();
        $formTypeOptions = $dto->getFormTypeOptions();

        self::assertFalse($formTypeOptions['required']);
    }

    public function testFieldHasReadonlyAttribute(): void
    {
        $user = $this->createUser('test-token');
        $this->admin->method('getSubject')->willReturn($user);

        $field = $this->createField();
        $result = $field->getEasyAdminField();

        self::assertInstanceOf(FieldInterface::class, $result);

        $dto = $result->getAsDto();
        $formTypeOptions = $dto->getFormTypeOptions();

        self::assertIsArray($formTypeOptions['attr']);
        /** @var array<string, mixed> $attr */
        $attr = $formTypeOptions['attr'];
        self::assertTrue($attr['readonly']);
    }
}
