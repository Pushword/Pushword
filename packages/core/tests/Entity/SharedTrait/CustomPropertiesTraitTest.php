<?php

namespace Pushword\Core\Tests\Entity\SharedTrait;

use Error;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Page;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
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
