<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Admin;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Newsletter\Entity\Audience;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

#[Group('integration')]
final class AudiencePresentationTest extends AbstractAdminTestClass
{
    /** @return iterable<string, array{string, int, string}> */
    public static function contactCounts(): iterable
    {
        yield 'empty' => ['en', 0, '0'];
        yield 'one' => ['en', 1, '1'];
        yield 'English' => ['en', 1234567, '1,234,567'];
        yield 'French' => ['fr', 1234567, "1\u{202F}234\u{202F}567"];
        yield 'German' => ['de', 1234567, '1.234.567'];
        yield 'Italian' => ['it', 1234567, '1.234.567'];
        yield 'Dutch' => ['nl', 1234567, '1.234.567'];
    }

    #[DataProvider('contactCounts')]
    public function testContactCountsUseTheCurrentLocale(string $locale, int $count, string $expected): void
    {
        self::bootKernel();
        $request = Request::create('/admin/newsletter/audience');
        $request->setLocale($locale);

        $requests = self::getContainer()->get(RequestStack::class);
        $requests->push($request);

        try {
            $html = self::getContainer()->get(Environment::class)->render('@PushwordNewsletter/admin/field/audience_contact_count.html.twig', [
                'field' => ['formattedValue' => $count],
            ]);
            self::assertSame($expected, trim(strip_tags($html)));
        } finally {
            $requests->pop();
        }
    }

    public function testLongNamesAndReferencesAreNotTruncated(): void
    {
        $client = $this->loginUser();
        $client->disableReboot();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $audience = new Audience();
        $audience->name = trim(str_repeat('Extended audience & travel updates ', 6));
        $audience->slug = 'presentation-'.str_repeat('long-reference-', 5).'list';
        $audience->mainHost = 'localhost.dev';
        $audience->fromEmail = 'newsletter@localhost.dev';

        $em->persist($audience);
        $em->flush();

        $id = $audience->id;
        self::assertIsInt($id);

        try {
            $crawler = $client->request(Request::METHOD_GET, '/admin/newsletter/audience', ['query' => $audience->slug]);
            self::assertResponseIsSuccessful();
            $row = $crawler->filter('tr[data-id="'.$id.'"]');
            self::assertSame($audience->name, $row->filter('td[data-column="name"]')->text());
            self::assertSame($audience->slug, $row->filter('td[data-column="slug"]')->text());
        } finally {
            $em->getConnection()->executeStatement('DELETE FROM newsletter_audience WHERE id = ?', [$id]);
        }
    }
}
