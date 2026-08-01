<?php

namespace Pushword\Flat\Tests\Serializer;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Flat\Serializer\PageFileSerializer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class PageFileSerializerTest extends KernelTestCase
{
    private PageFileSerializer $serializer;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->serializer = self::getContainer()->get(PageFileSerializer::class);
    }

    public function testParsePreservesBodyBytesAroundPaddedRule(): void
    {
        $body = "Intro paragraph.\n\n---\n\nSecond part after the rule.";
        $document = $this->serializer->parse("---\ntitle: Test\nrevision: abc123 # read only\n---\n\n".$body);

        self::assertSame(['title' => 'Test', 'revision' => 'abc123'], $document->matter());
        self::assertSame($body, $document->body());
    }

    public function testParsePreservesTightRuleSetextHeading(): void
    {
        // The plain Spatie parser split on every `---` line and re-joined with
        // fixed padding, turning `A\n---\nB` (a setext heading) into
        // `A\n\n---\n\nB` (a rule) — silent byte and rendering corruption.
        $body = "Section title\n---\nBody right under the underline.";
        $document = $this->serializer->parse("---\nt: 1\n---\n\n".$body);

        self::assertSame($body, $document->body());
    }

    public function testParseStripsUtf8Bom(): void
    {
        $document = $this->serializer->parse("\xEF\xBB\xBF---\ntitle: Bom\n---\n\nBody.");

        self::assertSame(['title' => 'Bom'], $document->matter());
    }

    public function testParseWithoutLeadingFrontmatterIsBodyOnly(): void
    {
        // Two body rules must not be mistaken for a front-matter block when the
        // document does not open with one.
        $text = "First part.\n\n---\n\nSecond.\n\n---\n\nThird.";
        $document = $this->serializer->parse($text);

        self::assertSame([], $document->matter());
        self::assertSame($text, $document->body());
    }
}
