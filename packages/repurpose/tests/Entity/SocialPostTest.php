<?php

namespace Pushword\Repurpose\Tests\Entity;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Entity\SocialPost;

/**
 * setSpec is the single writer of a SocialPost's queryable columns: it stores the
 * carousel payload and denormalises page/network/format/status/plannedAt out of it
 * so the admin can filter without decoding JSON. Pure-object assertions, no database.
 */
#[Group('integration')]
final class SocialPostTest extends TestCase
{
    public function testSetSpecDerivesQueryableColumns(): void
    {
        $post = new SocialPost();
        $post->setSpec([
            'page' => 'blog/x',
            'network' => 'linkedin',
            'format' => 'linkedin-4-5',
            'status' => 'planned',
            'plannedAt' => '2026-07-20T10:00:00+00:00',
            'slides' => [['title' => 'Hi']],
        ]);

        self::assertSame('blog/x', $post->getPage());
        self::assertSame('linkedin', $post->getNetwork());
        self::assertSame('linkedin-4-5', $post->getFormat());
        self::assertSame('planned', $post->getStatus());

        $plannedAt = $post->getPlannedAt();
        self::assertInstanceOf(DateTimeImmutable::class, $plannedAt);
        self::assertSame('2026-07-20 10:00', $plannedAt->format('Y-m-d H:i'));
    }

    public function testSetSpecKeepsColumnDefaultsAndNullsPlannedAtWhenAbsent(): void
    {
        $post = new SocialPost();
        $post->setSpec(['page' => 'x', 'network' => 'instagram', 'format' => 'instagram-4-5']);

        self::assertSame('draft', $post->getStatus());
        self::assertNull($post->getPlannedAt());
    }

    public function testSetSpecKeepsExistingColumnWhenSpecOmitsIt(): void
    {
        $post = new SocialPost();
        $post->setPage('blog/original');
        $post->setSpec(['network' => 'linkedin']);

        self::assertSame('blog/original', $post->getPage());
        self::assertSame('linkedin', $post->getNetwork());
    }

    public function testGetSpecReturnsWhatWasStored(): void
    {
        $spec = ['page' => 'x', 'network' => 'linkedin', 'slides' => [['title' => 'A']]];

        $post = new SocialPost();
        $post->setSpec($spec);

        self::assertSame($spec, $post->getSpec());
    }
}
