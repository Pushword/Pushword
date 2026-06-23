<?php

namespace Pushword\Conversation\Tests\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Entity\Review;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class ReviewReplySiteNameTest extends KernelTestCase
{
    /**
     * Both front templates resolve the reply author identically and are documented
     * as override points, so every scenario runs against each of them.
     *
     * @return iterable<string, array{string}>
     */
    public static function templateProvider(): iterable
    {
        yield 'review' => ['@PushwordConversation/conversation/review.html.twig'];
        yield 'reviewTruncated' => ['@PushwordConversation/conversation/reviewTruncated.html.twig'];
    }

    private function renderReply(string $template, string $replyAuthor, string $defaultReplyAuthor, string $siteName): string
    {
        self::bootKernel();

        $page = new Page();
        $page->host = 'localhost.dev';
        $page->slug = 'test-page';
        $page->locale = 'en';

        $review = new Review();
        $review->setContent('A great experience.');
        $review->setReply('Thank you!');
        $review->setReplyAuthor('' === $replyAuthor ? null : $replyAuthor);

        $twig = self::getContainer()->get('twig');

        return $twig->render($template, [
            'review' => $review,
            'page' => $page,
            'defaultReplyAuthor' => $defaultReplyAuthor,
            'siteName' => $siteName,
        ]);
    }

    #[DataProvider('templateProvider')]
    public function testSiteNamePlaceholderResolvedInReviewReplyAuthor(string $template): void
    {
        $html = $this->renderReply($template, 'Team %siteName%', '', 'Acme');

        self::assertStringContainsString('Reply from Team Acme', $html);
        self::assertStringNotContainsString('%siteName%', $html);
    }

    #[DataProvider('templateProvider')]
    public function testSiteNamePlaceholderResolvedInDefaultReplyAuthor(string $template): void
    {
        $html = $this->renderReply($template, '', 'The %siteName% crew', 'Acme');

        self::assertStringContainsString('Reply from The Acme crew', $html);
        self::assertStringNotContainsString('%siteName%', $html);
    }

    #[DataProvider('templateProvider')]
    public function testReplyAuthorWithoutPlaceholderRendersUnchanged(string $template): void
    {
        $html = $this->renderReply($template, 'Robin, Founder', '', 'Acme');

        self::assertStringContainsString('Reply from Robin, Founder', $html);
    }
}
