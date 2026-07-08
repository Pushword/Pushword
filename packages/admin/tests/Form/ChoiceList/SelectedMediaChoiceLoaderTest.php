<?php

namespace Pushword\Admin\Tests\Form\ChoiceList;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Admin\Form\ChoiceList\SelectedMediaChoiceLoader;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;

/**
 * Unit coverage for the lazy media choice loader: the render list is only the
 * seed (never the whole library), and the submit-time lookup is decoupled from
 * it — a picked id absent from the render list is resolved by direct id query.
 */
#[Group('unit')]
final class SelectedMediaChoiceLoaderTest extends TestCase
{
    public function testRenderListIsOnlyTheSeedAndNeverQueriesTheDatabase(): void
    {
        $repo = $this->createMock(MediaRepository::class);
        $repo->expects(self::never())->method('findBy');

        $selected = new Media();
        $loader = new SelectedMediaChoiceLoader($repo, [$selected]);

        self::assertSame([$selected], array_values($loader->loadChoiceList()->getChoices()));
    }

    public function testSetSeedFeedsTheRenderList(): void
    {
        $repo = $this->createMock(MediaRepository::class);
        $repo->expects(self::never())->method('findBy');

        $selected = new Media();
        $loader = new SelectedMediaChoiceLoader($repo);
        $loader->setSeed([$selected]);

        self::assertSame([$selected], array_values($loader->loadChoiceList()->getChoices()));
    }

    public function testSubmittedIdIsResolvedByDirectLookupEvenWhenNotInTheSeed(): void
    {
        $picked = new Media();
        $repo = $this->createMock(MediaRepository::class);
        $repo->expects(self::once())
            ->method('findBy')
            ->with(['id' => [7]])
            ->willReturn([$picked]);

        // The seed holds a different media; the picked id is not in the render list.
        $loader = new SelectedMediaChoiceLoader($repo, [new Media()]);

        self::assertSame([$picked], $loader->loadChoicesForValues(['7']));
    }

    public function testInvalidValuesAreFilteredAndNeverHitTheDatabase(): void
    {
        $repo = $this->createMock(MediaRepository::class);
        $repo->expects(self::never())->method('findBy');

        $loader = new SelectedMediaChoiceLoader($repo);

        self::assertSame([], $loader->loadChoicesForValues(['0', 'abc', '']));
        self::assertSame([], $loader->loadChoicesForValues([]));
    }
}
