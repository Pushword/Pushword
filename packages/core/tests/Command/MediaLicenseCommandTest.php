<?php

namespace Pushword\Core\Tests\Command;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\License\MediaLicense;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Tests\Image\License\ImageMetadataFixture;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Day-one backfill: the existing library never went through an upload hook.
 */
#[Group('integration')]
final class MediaLicenseCommandTest extends KernelTestCase
{
    private const array SEED = [
        'license' => 'https://altimood.test/mentions-legales',
        'creditText' => 'Altimood',
        'creator' => [['name' => 'Altimood', 'type' => 'Organization']],
    ];

    private EntityManagerInterface $em;

    private MediaStorageAdapter $mediaStorage;

    /** @var Media[] */
    private array $created = [];

    /** @var string[] */
    private array $writtenFiles = [];

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->em = $em;

        /** @var MediaStorageAdapter $mediaStorage */
        $mediaStorage = self::getContainer()->get(MediaStorageAdapter::class);
        $this->mediaStorage = $mediaStorage;

        $this->configureSeed(self::SEED);
    }

    protected function tearDown(): void
    {
        foreach ($this->created as $media) {
            if ($this->em->contains($media)) {
                $this->em->remove($media);
            }
        }

        $this->em->flush();

        foreach ($this->writtenFiles as $fileName) {
            if ($this->mediaStorage->fileExists($fileName)) {
                $this->mediaStorage->delete($fileName);
            }
        }

        $this->configureSeed([]);
        parent::tearDown();
    }

    /** @param array<string, mixed> $seed */
    private function configureSeed(array $seed): void
    {
        /** @var SiteRegistry $apps */
        $apps = self::getContainer()->get(SiteRegistry::class);
        $apps->get()->setCustomProperty('media_default_license_seed', $seed);
    }

    /**
     * Writes the file straight into storage and the row straight into the database —
     * the shape of a library that predates the feature.
     */
    private function existingMedia(string $name, string $xmp = ''): Media
    {
        $fileName = 'license-backfill-'.$name.'.jpg';
        $temporaryPath = sys_get_temp_dir().'/'.$fileName;
        ImageMetadataFixture::write($temporaryPath, $xmp);

        $this->mediaStorage->write($fileName, (string) file_get_contents($temporaryPath));
        unlink($temporaryPath);
        $this->writtenFiles[] = $fileName;

        $media = new Media();
        $media->setFileName($fileName);
        $media->setMimeType('image/jpeg');
        $media->setAlt($name);

        $this->em->persist($media);
        $this->em->flush();
        $this->created[] = $media;

        return $media;
    }

    private function backfill(string ...$options): CommandTester
    {
        $application = new Application(self::$kernel ?? throw new LogicException());
        $tester = new CommandTester($application->find('pw:media:license'));

        $input = ['--format' => 'agent'];
        foreach ($options as $option) {
            $input[$option] = true;
        }

        $tester->execute($input);

        return $tester;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(CommandTester $tester): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(trim($tester->getDisplay()), true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }

    public function testBackfillSeedsAndReportsTheExceptions(): void
    {
        $owned = $this->existingMedia('owned');
        $thirdParty = $this->existingMedia('third-party', ImageMetadataFixture::packet(
            '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            .'<dc:creator><rdf:Seq><rdf:li>Enrico Romanzi</rdf:li></rdf:Seq></dc:creator></rdf:Description>',
        ));

        $report = $this->decode($this->backfill());

        self::assertGreaterThanOrEqual(1, $report['seeded']);
        self::assertGreaterThanOrEqual(1, $report['thirdParty']);

        $this->em->refresh($owned);
        $this->em->refresh($thirdParty);

        self::assertSame(MediaLicense::STATE_SEEDED, $owned->licenseState);
        self::assertSame('Altimood', $owned->getCustomPropertyScalar(MediaLicense::CREDIT_TEXT));

        self::assertSame(MediaLicense::STATE_THIRD_PARTY, $thirdParty->licenseState);
        self::assertSame([['name' => 'Enrico Romanzi', 'type' => 'Person']], MediaLicense::creators($thirdParty));
        self::assertNull($thirdParty->getCustomProperty(MediaLicense::LICENSE));

        // The exceptions are listed by name so a human can decide on each of them.
        $names = array_column((array) $report['exceptions'], 'fileName');
        self::assertContains($thirdParty->getFileName(), $names);
    }

    public function testDryRunWritesNothing(): void
    {
        $media = $this->existingMedia('dry-run');

        $report = $this->decode($this->backfill('--dry-run'));

        self::assertTrue($report['dryRun']);
        self::assertGreaterThanOrEqual(1, $report['seeded']);

        $this->em->refresh($media);
        self::assertSame(MediaLicense::STATE_NONE, $media->licenseState);
        self::assertSame([], MediaLicense::extract($media));
    }

    /** --force is a human asserting the site owns what the file credits to somebody else. */
    public function testForceAppliesTheSiteLicenseOverThirdPartyRights(): void
    {
        $media = $this->existingMedia('forced', ImageMetadataFixture::packet(
            '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            .'<dc:creator><rdf:Seq><rdf:li>Enrico Romanzi</rdf:li></rdf:Seq></dc:creator></rdf:Description>',
        ));

        $this->backfill('--force');
        $this->em->refresh($media);

        self::assertSame(MediaLicense::STATE_OVERRIDDEN, $media->licenseState);
        self::assertSame('https://altimood.test/mentions-legales', $media->getCustomPropertyScalar(MediaLicense::LICENSE));
        self::assertSame([['name' => 'Altimood', 'type' => 'Organization']], MediaLicense::creators($media));
    }

    /** A second pass must not undo what the first decided. */
    public function testAlreadyDecidedMediaAreSkipped(): void
    {
        $media = $this->existingMedia('skipped');
        $this->backfill();
        $this->em->refresh($media);
        self::assertSame(MediaLicense::STATE_SEEDED, $media->licenseState);

        $media->setCustomProperty(MediaLicense::CREDIT_TEXT, 'Robin');
        $this->em->flush();
        self::assertSame(MediaLicense::STATE_OVERRIDDEN, $media->licenseState);

        $this->backfill('--all');
        $this->em->refresh($media);

        self::assertSame(MediaLicense::STATE_OVERRIDDEN, $media->licenseState);
        self::assertSame('Robin', $media->getCustomPropertyScalar(MediaLicense::CREDIT_TEXT));
    }

    public function testTextFormatListsTheExceptions(): void
    {
        $this->existingMedia('reported', ImageMetadataFixture::packet(
            '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            .'<dc:creator><rdf:Seq><rdf:li>Enrico Romanzi</rdf:li></rdf:Seq></dc:creator></rdf:Description>',
        ));

        $application = new Application(self::$kernel ?? throw new LogicException());
        $tester = new CommandTester($application->find('pw:media:license'));
        $tester->execute(['--format' => 'text', '--dry-run' => true]);

        self::assertStringContainsString('Enrico Romanzi', $tester->getDisplay());
        self::assertStringContainsString('--force', $tester->getDisplay());
    }
}
