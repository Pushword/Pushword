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

final class CustomPropertiesTraitTest extends TestCase
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

        self::assertEmpty($customProperties->customProperties);

        $customProperties->customProperties = self::customPorperties();

        self::assertSame($customProperties->customProperties, self::customPorperties());
        self::assertSame($customProperties->getUnmanagedPropertiesAsYaml(), self::unmanagedPropertiesYaml());

        $customProperties->setUnmanagedPropertiesFromYaml(self::unmanagedPropertiesYaml('test 1234'), true);
        self::assertSame(self::customPorperties('test 1234'), $customProperties->customProperties);

        $customProperties->removeCustomProperty('newCustomPropertyNotIndexed');
        self::assertArrayNotHasKey('newCustomPropertyNotIndexed', $customProperties->customProperties);
    }

    public function testPreservedPropertySurvivesTheMergeButStaysInTheTextarea(): void
    {
        $page = new Page();
        $page->customProperties = ['apiWritten' => 'foo'];
        $page->preserveCustomProperty('apiWritten');

        // Preserved = shielded from the destructive reconciliation, NOT hidden.
        self::assertStringContainsString('apiWritten', $page->getUnmanagedPropertiesAsYaml());

        $page->setUnmanagedPropertiesFromYaml('other: kept', true);
        self::assertSame('foo', $page->getCustomProperty('apiWritten'));
        self::assertSame('kept', $page->getCustomProperty('other'));

        // And the textarea can still overwrite it — no throw, field-less key.
        $page->setUnmanagedPropertiesFromYaml("apiWritten: changed\nother: kept", true);
        self::assertSame('changed', $page->getCustomProperty('apiWritten'));
    }

    public function testSchemaPropertyIsHiddenSurvivesTheMergeAndAcceptsAStaleTextareaValue(): void
    {
        $page = new Page();
        $page->customProperties = ['author' => 'Robin'];
        $page->registerSchemaPropertyKey('author');

        // Hidden like a managed key: a generated field renders it instead.
        self::assertSame('', $page->getUnmanagedPropertiesAsYaml());

        // An empty textarea must not wipe it.
        $page->setUnmanagedPropertiesFromYaml('', true);
        self::assertSame('Robin', $page->getCustomProperty('author'));

        // A stale form (opened before the field existed) still carries the key
        // in the textarea: the typed value writes through instead of throwing.
        $page->setUnmanagedPropertiesFromYaml('author: Alice', true);
        self::assertSame('Alice', $page->getCustomProperty('author'));
    }

    /**
     * The columns lost their accessors to property hooks; __call() must answer those
     * names from the property, not from the bag — a template still calling
     * `page.getTitle()` rendered empty when it read an unset custom property.
     */
    public function testAGetterNamedCallIsAnsweredByThePropertyThatReplacedIt(): void
    {
        $page = new Page();
        $page->title = 'From the column';
        $page->setCustomProperty('title', 'From the bag');

        self::assertSame('From the column', $page->getTitle()); // @phpstan-ignore-line
        self::assertSame('From the column', $page->title()); // @phpstan-ignore-line
        self::assertSame('From the bag', $page->getCustomProperty('title'));
    }

    public function testACallNamingNoPropertyStillReadsTheCustomProperty(): void
    {
        $page = new Page();
        $page->setCustomProperty('somethingCustom', 'from the bag');

        self::assertSame('from the bag', $page->getSomethingCustom()); // @phpstan-ignore-line
        self::assertSame('from the bag', $page->somethingCustom()); // @phpstan-ignore-line
        self::assertNull($page->getNeverSet()); // @phpstan-ignore-line
    }

    public function testManagedPropertyKeyIsHidden(): void
    {
        $customProperties = new Page();
        $customProperties->customProperties = ['handledExternally' => 'foo'];

        self::assertStringContainsString('handledExternally', $customProperties->getUnmanagedPropertiesAsYaml());

        $customProperties->registerManagedPropertyKey('handledExternally');

        self::assertSame('', $customProperties->getUnmanagedPropertiesAsYaml());
    }

    public function testMergeEmptyYamlClearsUnmanagedProperties(): void
    {
        $page = new Page();
        $page->customProperties = ['unmanaged' => 'val', 'other' => 'val2'];

        $page->setUnmanagedPropertiesFromYaml('', true);

        self::assertSame([], $page->customProperties);
    }

    public function testMergeEmptyYamlKeepsManagedProperties(): void
    {
        $page = new Page();
        $page->customProperties = ['managed' => 'keep', 'unmanaged' => 'remove'];
        $page->registerManagedPropertyKey('managed');

        $page->setUnmanagedPropertiesFromYaml('', true);

        self::assertSame(['managed' => 'keep'], $page->customProperties);
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
        $page->customProperties = ['keepThis' => 'a', 'removeThis' => 'b'];

        // YAML only has 'keepThis', so 'removeThis' should be removed
        $yaml = Yaml::dump(['keepThis' => 'updated']);
        $page->setUnmanagedPropertiesFromYaml($yaml, true);

        self::assertSame('updated', $page->getCustomProperty('keepThis'));
        self::assertNull($page->getCustomProperty('removeThis'));
    }

    public function testValidationWithoutYamlSurfaceKeepsCustomProperties(): void
    {
        // Reproduces the API flow: customProperties are set directly and the page
        // is validated without the admin YAML textarea ever feeding a value.
        $page = new Page();
        $page->customProperties = ['productCode' => 'ABC-123'];

        $page->validateUnmanagedProperties($this->getExceptionContextInterface());

        self::assertSame(['productCode' => 'ABC-123'], $page->customProperties);
    }

    public function testMergeThrowsWhenYamlContainsManagedProperty(): void
    {
        $page = new Page();
        $page->registerManagedPropertyKey('managed');

        $yaml = Yaml::dump(['managed' => 'forbidden']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('managed');

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
        $page->customProperties = [
            'managed' => 'stays',
            'old_unmanaged' => 'removed',
            'kept_unmanaged' => 'updated',
        ];
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

    protected function getExceptionContextInterface(): ExecutionContextInterface&MockObject
    {
        $mockConstraintViolationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $mockConstraintViolationBuilder->method('atPath')->willReturnSelf();
        $mockConstraintViolationBuilder->method('addViolation')->willReturnSelf();

        $mock = $this->createMock(ExecutionContextInterface::class);
        $mock->method('buildViolation')->willReturnCallback(static function (string $arg) use ($mockConstraintViolationBuilder): MockObject {
            if (\in_array($arg, ['pageCustomPropertiesMalformed', 'pageCustomPropertiesNotStandAlone'], true)) {
                throw new Error();
            }

            return $mockConstraintViolationBuilder;
        });

        return $mock;
    }
}
