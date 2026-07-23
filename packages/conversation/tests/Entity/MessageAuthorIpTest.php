<?php

namespace Pushword\Conversation\Tests\Entity;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Entity\Message;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;

final class MessageAuthorIpTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;

    private ?int $createdMessageId = null;

    protected function tearDown(): void
    {
        if (null !== $this->entityManager && null !== $this->createdMessageId) {
            try {
                $message = $this->entityManager->find(Message::class, $this->createdMessageId);
                if (null !== $message) {
                    $this->entityManager->remove($message);
                    $this->entityManager->flush();
                }
            } catch (Throwable) {
                // Ignore errors during cleanup
            }
        }

        parent::tearDown();
    }

    /** @return Iterator<string, array{string, string, int}> */
    public static function ipProvider(): Iterator
    {
        yield 'loopback' => ['127.0.0.1', '127.0.0.0', 2130706432];
        yield 'last signed-int-safe ip' => ['127.255.255.7', '127.255.255.0', 2147483392];
        yield 'first overflowing ip' => ['128.0.0.1', '128.0.0.0', 2147483648];
        yield 'french isp range' => ['176.190.1.42', '176.190.1.0', 2965242112];
        yield 'broadcast' => ['255.255.255.255', '255.255.255.0', 4294967040];
    }

    #[DataProvider('ipProvider')]
    public function testAuthorIpRawRoundTrip(string $ip, string $anonymized, int $expectedLong): void
    {
        $message = new Message();
        $message->setAuthorIpRaw($ip);

        self::assertSame($expectedLong, $message->getAuthorIp());
        self::assertSame($anonymized, $message->getAuthorIpRaw());
    }

    public function testAuthorIpRawIsTrimmed(): void
    {
        $message = new Message();
        $message->setAuthorIpRaw("  176.190.1.42\n");

        self::assertSame(2965242112, $message->getAuthorIp());
    }

    /** @return Iterator<string, array{string}> */
    public static function unsupportedIpProvider(): Iterator
    {
        yield 'empty string' => [''];
        yield 'blank string' => ['   '];
        yield 'not an ip' => ['not-an-ip'];
        yield 'truncated ipv4' => ['176.190.1'];
        yield 'ipv6' => ['2a01:e0a:1f2:3::1']; // ip2long() has no IPv6 counterpart
    }

    #[DataProvider('unsupportedIpProvider')]
    public function testUnsupportedIpIsDiscarded(string $ip): void
    {
        $message = new Message();
        $message->setAuthorIpRaw($ip);

        self::assertNull($message->getAuthorIp());
        // Current behaviour: an unknown IP reads back as 0.0.0.0, not as an empty string.
        self::assertSame('0.0.0.0', $message->getAuthorIpRaw());
    }

    /**
     * The admin exposes authorIpRaw as a writable field only while the IP is unknown;
     * a null never overwrites an IP already collected from a visitor.
     */
    public function testSetAuthorIpIgnoresNull(): void
    {
        $message = new Message();
        $message->setAuthorIpRaw('176.190.1.42');
        $message->setAuthorIp(null);

        self::assertSame(2965242112, $message->getAuthorIp());
    }

    /**
     * ip2long() goes up to 4294967295, a signed INT stops at 2147483647:
     * every IPv4 >= 128.0.0.0 would blow up with "Out of range value" on MySQL.
     */
    #[Group('integration')]
    public function testAuthorIpColumnSpansTheWholeIpv4Range(): void
    {
        $metadata = $this->getEntityManager()->getClassMetadata(Message::class);
        $declaration = Type::getType((string) $metadata->getTypeOfField('authorIp'))
            ->getSQLDeclaration([], new MariaDBPlatform());

        self::assertSame('BIGINT', $declaration);
    }

    #[Group('integration')]
    #[DataProvider('ipProvider')]
    public function testAuthorIpIsPersistedAndRead(string $ip, string $anonymized, int $expectedLong): void
    {
        $entityManager = $this->getEntityManager();

        $message = new Message();
        $message->host = 'localhost.dev';
        $message->setContent('Message from '.$ip);
        $message->setReferring('/contact');
        $message->setAuthorIpRaw($ip);

        $entityManager->persist($message);
        $entityManager->flush();

        $this->createdMessageId = $message->id;

        $entityManager->clear();

        $reloaded = $entityManager->find(Message::class, $this->createdMessageId);
        self::assertInstanceOf(Message::class, $reloaded);
        self::assertSame($expectedLong, $reloaded->getAuthorIp());
        self::assertSame($anonymized, $reloaded->getAuthorIpRaw());
    }

    private function getEntityManager(): EntityManagerInterface
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $this->entityManager;
    }
}
