<?php

namespace Pushword\Flat\Tests\Serializer;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
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

    private function serializeBody(string $mainContent): string
    {
        $page = new Page(false);
        $page->slug = 'code-typo-page';
        $page->host = 'localhost.dev';
        $page->mainContent = $mainContent;

        return $this->serializer->serialize($page);
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

    /**
     * Typography normalization on export must not touch code: the render-time
     * Typographer never introduces typographic characters inside pre/code, so
     * a straightened code sample would render differently forever.
     */
    public function testSerializeKeepsCodeBytesWhileStraighteningProse(): void
    {
        $fence = "```php\n\$label = \"café\u{2026} déjà\u{A0}!\";\n```";
        $span = "`l\u{2019}exemple\u{2026}`";
        $serialized = $this->serializeBody(
            "Il dit\u{A0}: \u{201C}bonjour\u{2026}\u{201D}\n\n".$fence."\n\nVoir ".$span." et l\u{2019}ami\u{2026}"
        );

        self::assertStringContainsString($fence, $serialized);
        self::assertStringContainsString($span, $serialized);
        self::assertStringContainsString('Il dit : "bonjour..."', $serialized);
        self::assertStringContainsString("et l'ami...", $serialized);
    }

    public function testSerializeKeepsTildeFenceWithInfoStringAndLongerClosingRun(): void
    {
        $fence = "~~~text l\u{2019}info\ncafé\u{2026}\n~~~~";
        $serialized = $this->serializeBody($fence."\n\nl\u{2019}après\u{2026}");

        self::assertStringContainsString($fence, $serialized);
        self::assertStringContainsString("l'après...", $serialized);
    }

    public function testSerializeDoesNotCloseFenceOnShorterRun(): void
    {
        $fence = "````\ncafé\u{2026}\n```\nencore\u{2026}\n````";
        $serialized = $this->serializeBody($fence."\n\nl\u{2019}après");

        self::assertStringContainsString($fence, $serialized);
        self::assertStringContainsString("l'après", $serialized);
    }

    public function testSerializeKeepsUnclosedFenceToTheEnd(): void
    {
        $serialized = $this->serializeBody("l\u{2019}avant\n\n```\ncafé\u{2026}");

        self::assertStringContainsString("l'avant", $serialized);
        self::assertStringContainsString("```\ncafé\u{2026}", $serialized);
    }

    public function testSerializeKeepsMultiBacktickInlineSpan(): void
    {
        $span = "`` l\u{2019}a `b` \u{2026} ``";
        $serialized = $this->serializeBody("l\u{2019}un ".$span." et l\u{2019}autre\u{2026}");

        self::assertStringContainsString("l'un ".$span." et l'autre...", $serialized);
    }

    public function testSerializeNeverPairsACodeSpanAcrossABlankLine(): void
    {
        $serialized = $this->serializeBody("un ` deux\n\ntrois ` l\u{2019}quatre\u{2026}");

        self::assertStringContainsString("un ` deux\n\ntrois ` l'quatre...", $serialized);
    }
}
