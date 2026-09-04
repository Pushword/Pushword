<?php

namespace Pushword\Core\Tests\Service;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Service\MediaUploadValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Group('integration')]
final class MediaUploadValidatorTest extends KernelTestCase
{
    public function testRejectsExecutablePhpEvenWithAForgedClientMimeType(): void
    {
        self::bootKernel();
        $path = tempnam(sys_get_temp_dir(), 'pushword-upload-');
        self::assertIsString($path);
        file_put_contents($path, '<?php echo "owned";');

        $file = new UploadedFile($path, 'shell.php', 'image/png', null, true);

        $this->expectException(InvalidArgumentException::class);
        self::getContainer()->get(MediaUploadValidator::class)->validate($file);
    }

    public function testAcceptsARealImage(): void
    {
        self::bootKernel();
        $path = tempnam(sys_get_temp_dir(), 'pushword-upload-');
        self::assertIsString($path);
        $image = imagecreatetruecolor(1, 1);
        self::assertNotFalse($image);
        imagepng($image, $path);

        $file = new UploadedFile($path, 'pixel.png', 'image/png', null, true);
        self::getContainer()->get(MediaUploadValidator::class)->validate($file);

        self::addToAssertionCount(1);
    }
}
