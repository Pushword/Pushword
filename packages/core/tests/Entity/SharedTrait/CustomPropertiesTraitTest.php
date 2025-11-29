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

    protected static function standStandAloneCustomProperties(string $firstValue = 'test'): string
    {
        return Yaml::dump(['newCustomPropertyNotIndexed' => $firstValue]);
    }

    public function testStandAloneCustomProperties(): void
    {
        $customProperties = new Page();

        self::assertEmpty($customProperties->getCustomProperties());

        $customProperties->setCustomProperties(static::customPorperties());

        self::assertSame($customProperties->getCustomProperties(), static::customPorperties());
        self::assertSame($customProperties->getStandAloneCustomProperties(), static::standStandAloneCustomProperties());

        $customProperties->setStandAloneCustomProperties(static::standStandAloneCustomProperties('test 1234'), true);
        self::assertSame(static::customPorperties('test 1234'), $customProperties->getCustomProperties());

        self::assertFalse($customProperties->isStandAloneCustomProperty('customProperties'));

        $customProperties->removeCustomProperty('newCustomPropertyNotIndexed');
        self::assertArrayNotHasKey('newCustomPropertyNotIndexed', $customProperties->getCustomProperties());
    }

    public function testRegisteredCustomPropertyFieldIsHidden(): void
    {
        $customProperties = new Page();
        $customProperties->setCustomProperties(['handledExternally' => 'foo']);

        self::assertStringContainsString('handledExternally', $customProperties->getStandAloneCustomProperties());

        $customProperties->registerCustomPropertyField('handledExternally');

        self::assertSame('', $customProperties->getStandAloneCustomProperties());
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
