<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Service;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
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

    private function png(string $clientName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pushword-upload-');
        self::assertIsString($path);
        $image = imagecreatetruecolor(1, 1);
        self::assertNotFalse($image);
        imagepng($image, $path);

        return new UploadedFile($path, $clientName, 'image/png', null, true);
    }

    /** A constraint judges the name the file is about to be stored under. */
    public function testANewUploadIsNamedBeforeItIsValidated(): void
    {
        self::bootKernel();
        $media = new Media();
        $media->setMediaFile($this->png('Refuge du Goûter.PNG'));

        $violations = self::getContainer()->get(MediaUploadValidator::class)->validateMedia($media);

        self::assertCount(0, $violations);
        self::assertSame('refuge-du-gouter.png', $media->getFileName());
    }

    public function testTheAltNamesANewUploadAsAtPersist(): void
    {
        $media = new Media();
        $media->setMediaFile($this->png('IMG_0042.png'));
        $media->setAlt('Lac Blanc');

        $media->nameUpload();

        self::assertSame('lac-blanc.png', $media->getFileName());
    }

    public function testAChosenNameIsKept(): void
    {
        $media = new Media();
        $media->setMediaFile($this->png('IMG_0042.png'));
        $media->setFileName('chosen.png');

        $media->nameUpload();

        self::assertSame('chosen.png', $media->getFileName());
    }

    /** Nothing in flight, nothing to name: a media edited without a new file keeps ''. */
    public function testAMediaWithoutAFileIsLeftUnnamed(): void
    {
        $media = new Media();
        $media->setAlt('Lac Blanc');

        $media->nameUpload();

        self::assertSame('', $media->getFileName());
    }

    /** Over upload_max_filesize there is no file to name; Assert\File says so instead. */
    public function testARejectedUploadIsLeftForAssertFileToReport(): void
    {
        $media = new Media();
        $media->setMediaFile(new UploadedFile('', 'huge.png', 'image/png', \UPLOAD_ERR_INI_SIZE, true));

        $media->nameUpload();

        self::assertSame('', $media->getFileName());
    }
}
