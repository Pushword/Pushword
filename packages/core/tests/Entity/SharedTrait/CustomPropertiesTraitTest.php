<?php

namespace Pushword\Core\Tests\Entity\SharedTrait;

use Error;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Page;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class CustomPropertiesTraitTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    protected static function customPorperties(string $firstValue = 'test', string $secondValue = 'test 2'): array
    {
        return [
            'newCustomPropertyNotIndexed' => $firstValue,
            'customProperties' => $secondValue,
        ];
    }

    protected static function unmanagedPropertiesYaml(string $firstValue = 'test', string $secondValue = 'test 2'): string
    {
        return Yaml::dump(['newCustomPropertyNotIndexed' => $firstValue, 'customProperties' => $secondValue]);
    }

    public function testUnmanagedProperties(): void
    {
        $customProperties = new Page();

        self::assertEmpty($customProperties->getCustomProperties());

        $customProperties->setCustomProperties(static::customPorperties());

        self::assertSame($customProperties->getCustomProperties(), static::customPorperties());
        self::assertSame($customProperties->getUnmanagedPropertiesAsYaml(), static::unmanagedPropertiesYaml());

        $customProperties->setUnmanagedPropertiesFromYaml(static::unmanagedPropertiesYaml('test 1234'), true);
        self::assertSame(static::customPorperties('test 1234'), $customProperties->getCustomProperties());

        $customProperties->removeCustomProperty('newCustomPropertyNotIndexed');
        self::assertArrayNotHasKey('newCustomPropertyNotIndexed', $customProperties->getCustomProperties());
    }

    public function testManagedPropertyKeyIsHidden(): void
    {
        $customProperties = new Page();
        $customProperties->setCustomProperties(['handledExternally' => 'foo']);

        self::assertStringContainsString('handledExternally', $customProperties->getUnmanagedPropertiesAsYaml());

        $customProperties->registerManagedPropertyKey('handledExternally');

        self::assertSame('', $customProperties->getUnmanagedPropertiesAsYaml());
    }

    public function testMergeEmptyYamlClearsUnmanagedProperties(): void
    {
        $page = new Page();
        $page->setCustomProperties(['unmanaged' => 'val', 'other' => 'val2']);

        $page->setUnmanagedPropertiesFromYaml('', true);

        self::assertSame([], $page->getCustomProperties());
    }

    public function testMergeEmptyYamlKeepsManagedProperties(): void
    {
        $page = new Page();
        $page->setCustomProperties(['managed' => 'keep', 'unmanaged' => 'remove']);
        $page->registerManagedPropertyKey('managed');

        $page->setUnmanagedPropertiesFromYaml('', true);

        self::assertSame(['managed' => 'keep'], $page->getCustomProperties());
    }

    public function testMergeAddsNewPropertiesFromYaml(): void
    {
        $page = new Page();

        $yaml = Yaml::dump(['newProp' => 'newValue', 'another' => 42]);
        $page->setUnmanagedPropertiesFromYaml($yaml, true);

        self::assertSame('newValue', $page->getCustomProperty('newProp'));
        self::assertSame(42, $page->getCustomProperty('another'));
    }

    public function testMergeRemovesDeletedUnmanagedProperty(): void
    {
        $page = new Page();
        $page->setCustomProperties(['keepThis' => 'a', 'removeThis' => 'b']);

        // YAML only has 'keepThis', so 'removeThis' should be removed
        $yaml = Yaml::dump(['keepThis' => 'updated']);
        $page->setUnmanagedPropertiesFromYaml($yaml, true);

        self::assertSame('updated', $page->getCustomProperty('keepThis'));
        self::assertNull($page->getCustomProperty('removeThis'));
    }

    public function testMergeThrowsWhenYamlContainsManagedProperty(): void
    {
        $page = new Page();
        $page->registerManagedPropertyKey('managed');

        $yaml = Yaml::dump(['managed' => 'forbidden']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('managed');

        $page->setUnmanagedPropertiesFromYaml($yaml, true);
    }

    public function testMergeThrowsOnInvalidYaml(): void
    {
        $page = new Page();

        $this->expectException(ParseException::class);

        $page->setUnmanagedPropertiesFromYaml("invalid:\n  - [unclosed", true);
    }

    public function testMergeMixedScenario(): void
    {
        $page = new Page();
        $page->setCustomProperties([
            'managed' => 'stays',
            'old_unmanaged' => 'removed',
            'kept_unmanaged' => 'updated',
        ]);
        $page->registerManagedPropertyKey('managed');

        $yaml = Yaml::dump([
            'kept_unmanaged' => 'new_value',
            'brand_new' => true,
        ]);
        $page->setUnmanagedPropertiesFromYaml($yaml, true);

        self::assertSame('stays', $page->getCustomProperty('managed'));
        self::assertNull($page->getCustomProperty('old_unmanaged'));
        self::assertSame('new_value', $page->getCustomProperty('kept_unmanaged'));
        self::assertTrue($page->getCustomProperty('brand_new'));
    }

    public function testMergeYamlReturningScalarThrows(): void
    {
        $page = new Page();

        $this->expectException(InvalidArgumentException::class);

        $page->setUnmanagedPropertiesFromYaml('just a string', true);
    }

    protected function getExceptionContextInterface(): MockObject
    {
        $mockConstraintViolationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $mockConstraintViolationBuilder->method('atPath')->willReturnSelf();
        $mockConstraintViolationBuilder->method('addViolation')->willReturnSelf();

        $mock = $this->createMock(ExecutionContextInterface::class);
        $mock->method('buildViolation')->willReturnCallback(static function ($arg) use ($mockConstraintViolationBuilder): MockObject {
            if (\in_array($arg, ['pageCustomPropertiesMalformed', 'page.customProperties.notStandAlone'], true)) {
                throw new Error();
            }

            return $mockConstraintViolationBuilder;
        });

        return $mock;
    }
}
