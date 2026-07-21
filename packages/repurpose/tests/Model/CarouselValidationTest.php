<?php

namespace Pushword\Repurpose\Tests\Model;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Repurpose\Service\CarouselFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Group('integration')]
final class CarouselValidationTest extends KernelTestCase
{
    /**
     * @param array<string, mixed> $spec
     *
     * @return array<string, string> propertyPath => message
     */
    private function violations(array $spec): array
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        $carousel = new CarouselFactory()->fromArray($spec);

        $out = [];
        foreach ($validator->validate($carousel) as $violation) {
            $out[$violation->getPropertyPath()] = (string) $violation->getMessage();
        }

        return $out;
    }

    public function testMinimalValidSpecPasses(): void
    {
        $violations = $this->violations([
            'page' => 'blog/mon-article',
            'network' => 'linkedin',
            'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Hello', 'image' => ['media' => 'photo.jpg']]],
        ]);

        self::assertSame([], $violations);
    }

    public function testUnknownNetworkIsRejected(): void
    {
        $violations = $this->violations([
            'page' => 'x', 'network' => 'myspace', 'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Hi']],
        ]);

        self::assertArrayHasKey('network', $violations);
    }

    public function testFormatMustBeAllowedForNetwork(): void
    {
        $violations = $this->violations([
            'page' => 'x', 'network' => 'instagram', 'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Hi']],
        ]);

        self::assertArrayHasKey('format', $violations);
        self::assertStringContainsString('not allowed', $violations['format']);
    }

    public function testOverlayWithoutImageIsRejected(): void
    {
        $violations = $this->violations([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Hi', 'overlay' => 0.5]],
        ]);

        self::assertArrayHasKey('slides[0].overlay', $violations);
    }

    public function testEmptySlideIsRejected(): void
    {
        $violations = $this->violations([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [[]],
        ]);

        self::assertArrayHasKey('slides[0].title', $violations);
    }

    public function testFocusPointOutOfRangeIsRejected(): void
    {
        $violations = $this->violations([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Hi', 'image' => ['media' => 'p.jpg', 'focusX' => 2]]],
        ]);

        self::assertArrayHasKey('slides[0].image.focusX', $violations);
    }

    public function testTooManySlidesForNetworkIsRejected(): void
    {
        // Pinterest's hard cap is 5 slides.
        $slides = array_fill(0, 6, ['title' => 'Hi', 'image' => ['media' => 'p.jpg']]);
        $violations = $this->violations([
            'page' => 'x', 'network' => 'pinterest', 'format' => 'pinterest-2-3',
            'slides' => $slides,
        ]);

        self::assertArrayHasKey('slides', $violations);
    }

    public function testGuidanceDoesNotFailValidation(): void
    {
        // 12 LinkedIn slides breaks the "ideally under 10" *guidance* but no hard
        // limit (cap is 300 pages) — the validator must stay silent.
        $slides = array_fill(0, 12, ['title' => 'Hi', 'image' => ['media' => 'p.jpg']]);
        $violations = $this->violations([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => $slides,
        ]);

        self::assertSame([], $violations);
    }
}
