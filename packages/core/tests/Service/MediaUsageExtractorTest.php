<?php

namespace Pushword\Core\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\MediaUsage;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\MediaUsageExtractor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class MediaUsageExtractorTest extends KernelTestCase
{
    private MediaUsageExtractor $extractor;

    private string $knownFileName;

    private int $knownMediaId;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        /** @var MediaUsageExtractor $extractor */
        $extractor = self::getContainer()->get(MediaUsageExtractor::class);
        $this->extractor = $extractor;

        /** @var MediaRepository $mediaRepository */
        $mediaRepository = self::getContainer()->get(MediaRepository::class);
        $map = $mediaRepository->getFileNameToIdMap();
        self::assertArrayHasKey('1.jpg', $map, 'The fixtures are expected to hold 1.jpg');

        $this->knownFileName = '1.jpg';
        $this->knownMediaId = $map['1.jpg'];
    }

    public function testFindsAMediaReferencedByAMarkdownImage(): void
    {
        $usages = $this->extractor->extract('Text ![Caption](/media/default/'.$this->knownFileName.') text', [], null);

        self::assertSame([['mediaId' => $this->knownMediaId, 'source' => MediaUsage::SOURCE_CONTENT]], $usages);
    }

    public function testFindsAMediaBehindAnImageFilterPath(): void
    {
        $usages = $this->extractor->extract('<img src="/media/md/'.$this->knownFileName.'">', [], null);

        self::assertSame([['mediaId' => $this->knownMediaId, 'source' => MediaUsage::SOURCE_CONTENT]], $usages);
    }

    /**
     * The `str_contains()` this replaced counted `my1.jpg` as a use of `1.jpg`.
     * Whole filename-shaped runs is what stops that.
     */
    public function testDoesNotMatchAFilenameEmbeddedInALongerOne(): void
    {
        self::assertSame([], $this->extractor->extract('![](/media/default/my'.$this->knownFileName.')', [], null));
    }

    public function testReportsTheSameMediaOnceForOneSource(): void
    {
        $content = '![](/media/default/'.$this->knownFileName.') and again ![](/media/default/'.$this->knownFileName.')';

        self::assertCount(1, $this->extractor->extract($content, [], null));
    }

    public function testReportsTheMainImageWithItsOwnSource(): void
    {
        $usages = $this->extractor->extract('', [], $this->knownMediaId);

        self::assertSame([['mediaId' => $this->knownMediaId, 'source' => MediaUsage::SOURCE_MAIN_IMAGE]], $usages);
    }

    public function testAMediaUsedBothInTheContentAndAsMainImageIsReportedTwice(): void
    {
        $usages = $this->extractor->extract(
            '![](/media/default/'.$this->knownFileName.')',
            [],
            $this->knownMediaId,
        );

        self::assertSame([
            ['mediaId' => $this->knownMediaId, 'source' => MediaUsage::SOURCE_MAIN_IMAGE],
            ['mediaId' => $this->knownMediaId, 'source' => MediaUsage::SOURCE_CONTENT],
        ], $usages);
    }

    public function testFindsAMediaNestedInACustomProperty(): void
    {
        $usages = $this->extractor->extract('', [
            'gallery' => ['images' => ['/media/default/'.$this->knownFileName]],
        ], null);

        self::assertSame([['mediaId' => $this->knownMediaId, 'source' => MediaUsage::SOURCE_PROPERTY]], $usages);
    }

    public function testIgnoresFilenamesNoMediaAnswersTo(): void
    {
        self::assertSame([], $this->extractor->extract(
            'See example.com, read about.md and ![](/media/default/nothing-here.jpg)',
            [],
            null,
        ));
    }
}
