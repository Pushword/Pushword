<?php

namespace Pushword\Conversation\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Pushword\Conversation\Entity\Review;

final class ReviewReplyTest extends TestCase
{
    public function testReplyDefaultsToEmptyString(): void
    {
        $review = new Review();

        self::assertSame('', $review->getReply());
        self::assertFalse($review->hasCustomProperty('reply'));
    }

    public function testReplyGetterSetter(): void
    {
        $review = new Review();
        $review->setReply('Thank you for your feedback!');

        self::assertSame('Thank you for your feedback!', $review->getReply());
        self::assertSame('Thank you for your feedback!', $review->getCustomProperty('reply'));
    }

    public function testEmptyReplyRemovesCustomProperty(): void
    {
        $review = new Review();
        $review->setReply('Some reply');
        self::assertTrue($review->hasCustomProperty('reply'));

        $review->setReply('   ');
        self::assertFalse($review->hasCustomProperty('reply'));
        self::assertSame('', $review->getReply());

        $review->setReply('Another reply');
        $review->setReply(null);
        self::assertFalse($review->hasCustomProperty('reply'));
    }

    public function testReplyAuthorGetterSetter(): void
    {
        $review = new Review();

        self::assertSame('', $review->getReplyAuthor());
        self::assertFalse($review->hasCustomProperty('replyAuthor'));

        $review->setReplyAuthor('Robin, Founder');
        self::assertSame('Robin, Founder', $review->getReplyAuthor());
        self::assertSame('Robin, Founder', $review->getCustomProperty('replyAuthor'));

        $review->setReplyAuthor('   ');
        self::assertFalse($review->hasCustomProperty('replyAuthor'));
        self::assertSame('', $review->getReplyAuthor());

        $review->setReplyAuthor('The team');
        $review->setReplyAuthor(null);
        self::assertFalse($review->hasCustomProperty('replyAuthor'));
    }
}
