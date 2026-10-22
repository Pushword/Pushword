<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Service;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\User;
use Pushword\Core\Service\MediaAuditLog;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Nothing recorded who uploaded, renamed or removed a media, so a file gone missing
 * could only be traced through a slow-request log (production, 2026-10-15).
 */
#[Group('integration')]
final class MediaAuditLogTest extends KernelTestCase
{
    public function testCreationRenameAndRemovalAreLoggedWithUserNamesAndHash(): void
    {
        self::bootKernel();
        $records = $this->recordChannel();

        $user = new User();
        $user->email = 'editor@example.tld';
        self::getContainer()->get('security.token_storage')->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $image = imagecreatetruecolor(2, 2);
        self::assertNotFalse($image);
        imagesetpixel($image, 0, 0, random_int(1, 0xFFFFFF));
        $path = sys_get_temp_dir().'/audited-'.uniqid().'.png';
        imagepng($image, $path);
        $hash = sha1_file($path);

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $media = new Media();
        $media->setMediaFile(new UploadedFile($path, basename($path), 'image/png', null, true));

        $em->persist($media);
        $em->flush();

        $id = $media->id;
        $created = $media->getFileName();
        $media->setFileName('audited-renamed-'.uniqid().'.png');
        $em->flush();
        $renamed = $media->getFileName();
        $em->remove($media);
        $em->flush();

        $who = ['hash' => $hash, 'user' => 'editor@example.tld'];
        self::assertSame([
            ['Media created', ['media' => $id, 'fileName' => $created] + $who],
            ['Media renamed', ['media' => $id, 'from' => $created, 'to' => $renamed] + $who],
            ['Media removed', ['media' => $id, 'fileName' => $renamed] + $who],
        ], array_values(array_map(
            static fn (LogRecord $record): array => [$record->message, $record->context],
            array_filter($records->getRecords(), static fn (LogRecord $record): bool => Level::Info === $record->level),
        )));
    }

    /** The channel MediaAuditLog writes to, with a handler keeping what it receives. */
    private function recordChannel(): TestHandler
    {
        $logger = new ReflectionProperty(MediaAuditLog::class, 'logger')->getValue(self::getContainer()->get(MediaAuditLog::class));
        self::assertInstanceOf(Logger::class, $logger);
        self::assertSame('pushword_media', $logger->getName());

        $handler = new TestHandler();
        $logger->pushHandler($handler);

        return $handler;
    }
}
