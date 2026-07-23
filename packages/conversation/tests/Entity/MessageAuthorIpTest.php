<?php

namespace Pushword\Conversation\Tests\Entity;

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

    /** @return Iterator<string, array{string, string}> */
    public static function ipProvider(): Iterator
    {
        // The whole IPv4 space, including the upper half a signed INT column could not hold.
        yield 'loopback' => ['127.0.0.1', '127.0.0.0'];
        yield 'last signed-int-safe ip' => ['127.255.255.7', '127.255.255.0'];
        yield 'first ip past the old INT ceiling' => ['128.0.0.1', '128.0.0.0'];
        yield 'french isp range' => ['176.190.1.42', '176.190.1.0'];
        yield 'broadcast' => ['255.255.255.255', '255.255.255.0'];
        // IPv6, which ip2long() could never encode.
        yield 'ipv6' => ['2a01:e0a:1f2:3::1', '2a01:e0a:1f2:3::'];
        yield 'ipv6 full form' => ['2001:0db8:85a3:1234:0000:8a2e:0370:7334', '2001:db8:85a3:1234::'];
        yield 'ipv6 loopback' => ['::1', '::'];
        // IPv4 reaching us in mapped notation, e.g. behind a proxy normalizing to IPv6.
        yield 'ipv4-mapped ipv6' => ['::ffff:176.190.1.42', '::ffff:176.190.1.0'];
    }

    #[DataProvider('ipProvider')]
    public function testAuthorIpRawRoundTrip(string $ip, string $anonymized): void
    {
        $message = new Message();
        $message->setAuthorIpRaw($ip);

        self::assertSame($anonymized, $message->getAuthorIp());
        self::assertSame($anonymized, $message->getAuthorIpRaw());
    }

    public function testAuthorIpRawIsTrimmed(): void
    {
        $message = new Message();
        $message->setAuthorIpRaw("  176.190.1.42\n");

        self::assertSame('176.190.1.0', $message->getAuthorIp());
    }

    /** @return Iterator<string, array{string}> */
    public static function unsupportedIpProvider(): Iterator
    {
        yield 'empty string' => [''];
        yield 'blank string' => ['   '];
        yield 'not an ip' => ['not-an-ip'];
        yield 'truncated ipv4' => ['176.190.1'];
        yield 'out of range ipv4' => ['999.999.999.999'];
    }

    #[DataProvider('unsupportedIpProvider')]
    public function testUnsupportedIpIsDiscarded(string $ip): void
    {
        $message = new Message();
        $message->setAuthorIpRaw($ip);

        self::assertNull($message->getAuthorIp());
        // An unknown IP reads back as empty, never as a plausible-looking address.
        self::assertSame('', $message->getAuthorIpRaw());
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

        self::assertSame('176.190.1.0', $message->getAuthorIp());
    }

    #[Group('integration')]
    #[DataProvider('ipProvider')]
    public function testAuthorIpIsPersistedAndRead(string $ip, string $anonymized): void
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
        self::assertSame($anonymized, $reloaded->getAuthorIp());
    }

    private function getEntityManager(): EntityManagerInterface
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $this->entityManager;
    }
}
