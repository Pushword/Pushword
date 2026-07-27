<?php

namespace Pushword\Core\Tests\EventListener;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageRotator;
use Pushword\Core\Image\License\MediaLicense;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Tests\Image\License\ImageMetadataFixture;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * The upload decision, end to end: pre_upload reads the file, the state listener
 * persists the outcome.
 */
#[Group('integration')]
final class MediaLicenseSeedListenerTest extends KernelTestCase
{
    private const array SEED = [
        'license' => 'https://altimood.test/mentions-legales',
        'acquireLicensePage' => 'https://altimood.test/contact',
        'creditText' => 'Altimood',
        'creator' => [['name' => 'Altimood', 'type' => 'Organization']],
    ];

    private EntityManagerInterface $em;

    private string $dir = '';

    /** @var Media[] */
    private array $created = [];

    private bool $pushedRequest = false;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->em = $em;

        $this->dir = sys_get_temp_dir().'/pushword-seed-'.getmypid().'-'.uniqid();
        new Filesystem()->mkdir($this->dir);

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
        new Filesystem()->remove($this->dir);

        if ($this->pushedRequest) {
            /** @var RequestStack $requestStack */
            $requestStack = self::getContainer()->get('request_stack');
            $requestStack->pop();
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
     * @param array<string, string> $iptcIim
     * @param array<string, string> $exif
     */
    private function upload(string $name, string $xmp = '', array $iptcIim = [], array $exif = [], string $c2pa = ''): Media
    {
        $path = ImageMetadataFixture::write($this->dir.'/'.$name.'.jpg', $xmp, $iptcIim, $exif, $c2pa);

        $media = new Media();
        $media->setMediaFile(new UploadedFile($path, $name.'.jpg', 'image/jpeg', null, true));

        $this->em->persist($media);
        $this->em->flush();

        $this->created[] = $media;

        return $media;
    }

    /**
     * @param array<string, string> $iptcIim
     */
    private function replaceFile(Media $media, string $name, string $xmp = '', array $iptcIim = []): void
    {
        $path = ImageMetadataFixture::write($this->dir.'/'.$name.'.jpg', $xmp, $iptcIim);
        $media->setMediaFile(new UploadedFile($path, $name.'.jpg', 'image/jpeg', null, true));
        $this->em->flush();
    }

    private function creatorXmp(string $name): string
    {
        return ImageMetadataFixture::packet(
            '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            .'<dc:creator><rdf:Seq><rdf:li>'.$name.'</rdf:li></rdf:Seq></dc:creator></rdf:Description>',
        );
    }

    public function testAFileWithoutRightsIsSeeded(): void
    {
        $media = $this->upload('plain');

        self::assertSame(MediaLicense::STATE_SEEDED, $media->getLicenseState());
        self::assertSame('Altimood', $media->getCustomPropertyScalar(MediaLicense::CREDIT_TEXT));
        self::assertSame([['name' => 'Altimood', 'type' => 'Organization']], MediaLicense::creators($media));
    }

    public function testWithoutAConfiguredSeedNothingIsWritten(): void
    {
        $this->configureSeed([]);
        $media = $this->upload('no-seed');

        self::assertSame(MediaLicense::STATE_NONE, $media->getLicenseState());
        self::assertSame([], MediaLicense::extract($media));
    }

    public function testEmbeddedAuthorshipBlocksTheSiteLicense(): void
    {
        $media = $this->upload('third-party', $this->creatorXmp('Enrico Romanzi'));

        self::assertSame(MediaLicense::STATE_THIRD_PARTY, $media->getLicenseState());
        // A file gives bare names, so an imported creator falls back to Person.
        self::assertSame([['name' => 'Enrico Romanzi', 'type' => 'Person']], MediaLicense::creators($media));
        self::assertNull($media->getCustomProperty(MediaLicense::LICENSE));
        self::assertNull($media->getCustomProperty(MediaLicense::ACQUIRE_LICENSE_PAGE));
    }

    /** A rights claim is a rights claim, even without a name attached to it. */
    public function testAWebStatementAloneIsStillThirdParty(): void
    {
        $media = $this->upload('web-statement', ImageMetadataFixture::packet(
            '<rdf:Description rdf:about="" xmlns:xmpRights="http://ns.adobe.com/xap/1.0/rights/"'
            .' xmpRights:WebStatement="www.bodinphoto.com"/>',
        ));

        self::assertSame(MediaLicense::STATE_THIRD_PARTY, $media->getLicenseState());
        self::assertSame('https://www.bodinphoto.com', $media->getCustomPropertyScalar(MediaLicense::LICENSE));
    }

    /** Partial embedded rights are never topped up from the seed. */
    public function testTheSeedNeverFillsTheGapsOfAThirdPartyFile(): void
    {
        $media = $this->upload('partial', $this->creatorXmp('Dominique VIVARES'));

        self::assertNull($media->getCustomProperty(MediaLicense::COPYRIGHT_NOTICE));
        self::assertNull($media->getCustomProperty(MediaLicense::CREDIT_TEXT));
    }

    public function testAnAiGeneratedFileIsSeededAndItsCreditLineDropped(): void
    {
        $media = $this->upload(
            'ai',
            ImageMetadataFixture::packet('<rdf:Description rdf:about=""'
                .' xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/"'
                .' Iptc4xmpExt:DigitalSourceType="TrainedAlgorithmicMedia"/>'),
            iptcIim: ['2#110' => 'AI Generated'],
        );

        self::assertSame(MediaLicense::STATE_SEEDED, $media->getLicenseState());
        self::assertSame('Altimood', $media->getCustomPropertyScalar(MediaLicense::CREDIT_TEXT));
        self::assertSame(
            MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.'trainedAlgorithmicMedia',
            $media->getCustomPropertyScalar(MediaLicense::DIGITAL_SOURCE_TYPE),
        );
    }

    /** "AI Generated Studio Ltd" is an agency, not a generator marker. */
    public function testACreditLineMerelyContainingAiIsRealAndGates(): void
    {
        $media = $this->upload('ai-lookalike', iptcIim: ['2#110' => 'AI Generated Studio Ltd']);

        self::assertSame(MediaLicense::STATE_THIRD_PARTY, $media->getLicenseState());
        self::assertSame('AI Generated Studio Ltd', $media->getCustomPropertyScalar(MediaLicense::CREDIT_TEXT));
    }

    /** A claim found outside XMP gates just as one found inside it does. */
    public function testRightsFoundInIimGateTheSeed(): void
    {
        $media = $this->upload('iim-only', iptcIim: ['2#080' => 'O2Ephotos']);

        self::assertSame(MediaLicense::STATE_THIRD_PARTY, $media->getLicenseState());
        self::assertSame([['name' => 'O2Ephotos', 'type' => 'Person']], MediaLicense::creators($media));
    }

    /**
     * A file whose only metadata is a C2PA manifest — what gpt-image writes — used to
     * read as carrying nothing. It carries provenance, not a rights claim, so it is
     * still the site's to license; the generator note just has to survive.
     */
    public function testAC2paOnlyFileIsSeededAndKeepsItsProvenance(): void
    {
        $media = $this->upload('c2pa-only', c2pa: ImageMetadataFixture::c2paActions(
            MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.MediaLicense::TRAINED_ALGORITHMIC_MEDIA,
        ));

        self::assertSame(MediaLicense::STATE_SEEDED, $media->getLicenseState());
        self::assertSame(
            MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.MediaLicense::TRAINED_ALGORITHMIC_MEDIA,
            $media->getCustomPropertyScalar(MediaLicense::DIGITAL_SOURCE_TYPE),
        );
    }

    /**
     * The cost of the seed model, accepted by design: the config is written into each
     * media at upload, so changing it later reaches nobody. pw:media:license is what
     * propagates it.
     */
    public function testChangingTheSeedDoesNotReachExistingMedia(): void
    {
        $media = $this->upload('before-config-change');
        self::assertSame('Altimood', $media->getCustomPropertyScalar(MediaLicense::CREDIT_TEXT));

        $this->configureSeed(['creditText' => 'Someone Else'] + self::SEED);

        $this->em->clear();
        $reloaded = $this->em->getRepository(Media::class)->find($media->id);
        self::assertInstanceOf(Media::class, $reloaded);
        self::assertSame('Altimood', $reloaded->getCustomPropertyScalar(MediaLicense::CREDIT_TEXT));

        $this->created = [$reloaded];
    }

    // --- Replacing the file ---

    public function testReplacingAThirdPartyFileWithAnOwnedOneDropsTheOldRights(): void
    {
        $media = $this->upload('swap-in', $this->creatorXmp('Enrico Romanzi'));
        self::assertSame(MediaLicense::STATE_THIRD_PARTY, $media->getLicenseState());

        $this->replaceFile($media, 'swap-out');

        self::assertSame(MediaLicense::STATE_SEEDED, $media->getLicenseState());
        self::assertSame([['name' => 'Altimood', 'type' => 'Organization']], MediaLicense::creators($media));
    }

    /** The dangerous direction: the site's own licensing must not survive onto a stranger's photo. */
    public function testReplacingAnOwnedFileWithAThirdPartyOneDropsTheSiteLicense(): void
    {
        $media = $this->upload('owned');
        self::assertSame(MediaLicense::STATE_SEEDED, $media->getLicenseState());

        $this->replaceFile($media, 'now-third-party', $this->creatorXmp('Enrico Romanzi'));

        self::assertSame(MediaLicense::STATE_THIRD_PARTY, $media->getLicenseState());
        self::assertNull($media->getCustomProperty(MediaLicense::ACQUIRE_LICENSE_PAGE));
        self::assertNull($media->getCustomProperty(MediaLicense::LICENSE));
    }

    /** Re-uploading the same bytes is an accident, not a new licensing situation. */
    public function testReUploadingIdenticalBytesKeepsAHandWrittenCredit(): void
    {
        $media = $this->upload('identical');

        $media->setCustomProperty(MediaLicense::CREDIT_TEXT, 'Photo maison');

        $this->em->flush();
        self::assertSame(MediaLicense::STATE_OVERRIDDEN, $media->getLicenseState());

        // Vich moved the first upload into the media dir, so the same bytes have to be
        // written again — which is exactly what an accidental re-upload looks like.
        $again = ImageMetadataFixture::write($this->dir.'/identical-again.jpg');
        $media->setMediaFile(new UploadedFile($again, 'identical.jpg', 'image/jpeg', null, true));
        $this->em->flush();

        self::assertSame('Photo maison', $media->getCustomPropertyScalar(MediaLicense::CREDIT_TEXT));
        self::assertSame(MediaLicense::STATE_OVERRIDDEN, $media->getLicenseState());
    }

    /**
     * Rotation writes through MediaStorageAdapter and never sets a media file, so it
     * bypasses Vich entirely — turning an image the right way up is not a licensing
     * event and must not discard a hand-written credit.
     */
    public function testRotatingAnImageLeavesTheLicenseAlone(): void
    {
        $media = $this->upload('rotated');
        $media->setCustomProperty(MediaLicense::CREDIT_TEXT, 'Photo maison');

        $this->em->flush();

        /** @var ImageRotator $rotator */
        $rotator = self::getContainer()->get(ImageRotator::class);
        $rotator->rotate($media, 90);

        $this->em->flush();

        self::assertSame('Photo maison', $media->getCustomPropertyScalar(MediaLicense::CREDIT_TEXT));
        self::assertSame(MediaLicense::STATE_OVERRIDDEN, $media->getLicenseState());
    }

    /**
     * A truncated image still reads as a JPEG, so the decision runs — and finds
     * nothing. What must not happen is the previous photographer surviving next to the
     * new decision, which is what "fill only the empty fields" would have produced.
     */
    public function testReplacingWithACorruptImageLeavesNoHalfWrittenState(): void
    {
        $media = $this->upload('readable', $this->creatorXmp('Enrico Romanzi'));
        self::assertSame(MediaLicense::STATE_THIRD_PARTY, $media->getLicenseState());

        // Built fresh: Vich moved the uploaded file out of the temp directory.
        $truncated = ImageMetadataFixture::write($this->dir.'/truncated.jpg');
        file_put_contents($truncated, substr((string) file_get_contents($truncated), 0, 200));
        $media->setMediaFile(new UploadedFile($truncated, 'truncated.jpg', 'image/jpeg', null, true));
        $this->em->flush();

        self::assertSame([['name' => 'Altimood', 'type' => 'Organization']], MediaLicense::creators($media));
        self::assertSame(MediaLicense::STATE_SEEDED, $media->getLicenseState());
    }

    /**
     * A replacement that is not an image at all is not a licensing decision: the hook
     * does not run, so it writes nothing rather than writing half of one.
     */
    public function testReplacingWithANonImageWritesNothing(): void
    {
        $media = $this->upload('still-an-image', $this->creatorXmp('Enrico Romanzi'));
        $before = MediaLicense::extract($media);

        $notAnImage = $this->dir.'/notes.txt';
        file_put_contents($notAnImage, 'not an image at all');
        $media->setMediaFile(new UploadedFile($notAnImage, 'notes.txt', 'text/plain', null, true));
        $this->em->flush();

        self::assertSame($before, MediaLicense::extract($media));
        self::assertSame(MediaLicense::STATE_THIRD_PARTY, $media->getLicenseState());
    }

    // --- licenseState ---

    public function testEditingAValueMarksTheLicenseAsAsserted(): void
    {
        $media = $this->upload('edited');
        self::assertSame(MediaLicense::STATE_SEEDED, $media->getLicenseState());

        $media->setCustomProperty(MediaLicense::CREDIT_TEXT, 'Robin');
        $this->em->flush();

        self::assertSame(MediaLicense::STATE_OVERRIDDEN, $media->getLicenseState());
    }

    /**
     * The preUpdate changeset trap: a field assigned after the changeset was computed
     * is never written unless it is recomputed. Asserted against the database, not the
     * in-memory entity, which would pass either way.
     */
    public function testTheNewStateReachesTheDatabase(): void
    {
        $media = $this->upload('persisted');

        $media->setCustomProperty(MediaLicense::CREDIT_TEXT, 'Robin');

        $this->em->flush();
        $this->em->clear();

        $reloaded = $this->em->getRepository(Media::class)->find($media->id);
        self::assertInstanceOf(Media::class, $reloaded);
        self::assertSame(MediaLicense::STATE_OVERRIDDEN, $reloaded->getLicenseState());

        $this->created = [$reloaded];
    }

    public function testClearingEveryValueLeavesNoLicenseState(): void
    {
        $media = $this->upload('cleared');

        foreach (MediaLicense::KEYS as $key) {
            $media->removeCustomProperty($key);
        }

        $this->em->flush();

        self::assertSame(MediaLicense::STATE_NONE, $media->getLicenseState());
    }

    /** The listener needs a request in the stack to reach a flash bag at all. */
    private function pushRequest(bool $xhr = false, string $supplied = ''): FlashBagInterface
    {
        $request = new Request(request: '' === $supplied ? [] : ['embeddedMetadata' => $supplied]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        if ($xhr) {
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        }

        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);

        $this->pushedRequest = true;

        /** @var Session $session */
        $session = $request->getSession();

        return $session->getFlashBag();
    }

    private function flashText(FlashBagInterface $flashBag): string
    {
        $messages = [];

        foreach ($flashBag->peekAll() as $typeMessages) {
            foreach ((array) $typeMessages as $message) {
                if (\is_string($message)) {
                    $messages[] = $message;
                }
            }
        }

        return implode(' ', $messages);
    }

    private function trans(string $key): string
    {
        return self::getContainer()->get('translator')->trans($key, ['%rights%' => '']);
    }

    /**
     * @param array<string, string> $segments
     */
    private function supplied(array $segments): string
    {
        return json_encode(array_map(base64_encode(...), $segments), \JSON_THROW_ON_ERROR);
    }

    /**
     * The admin scales an image down through a canvas before uploading it, which keeps
     * no metadata — so the file arrives bare and its segments arrive beside it.
     */
    public function testRightsPostedBesideAStrippedFileStillGate(): void
    {
        $this->pushRequest(xhr: true, supplied: $this->supplied(['xmp' => $this->creatorXmp('Enrico Romanzi')]));
        $media = $this->upload('supplied-third-party');

        self::assertSame(MediaLicense::STATE_THIRD_PARTY, $media->getLicenseState());
        self::assertSame([['name' => 'Enrico Romanzi', 'type' => 'Person']], $media->getCustomProperty(MediaLicense::CREATOR));
    }

    /** A gpt-image PNG has nothing else: no XMP, no IPTC, no EXIF. */
    public function testProvenancePostedBesideAStrippedFileSurvives(): void
    {
        $c2pa = ImageMetadataFixture::c2paActions(MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.MediaLicense::TRAINED_ALGORITHMIC_MEDIA);
        $this->pushRequest(xhr: true, supplied: $this->supplied(['c2pa' => $c2pa]));
        $media = $this->upload('supplied-provenance');

        self::assertSame(
            MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.MediaLicense::TRAINED_ALGORITHMIC_MEDIA,
            $media->getCustomProperty(MediaLicense::DIGITAL_SOURCE_TYPE),
        );
        // Provenance is not a rights claim, so the site still licenses the image.
        self::assertSame(MediaLicense::STATE_SEEDED, $media->getLicenseState());
    }

    /**
     * The sidecar is posted by a client and can say anything. It only ever fills what
     * the stored bytes leave empty, so it cannot rewrite a credit we actually hold.
     */
    public function testTheStoredFileOutranksWhatIsPostedBesideIt(): void
    {
        $this->pushRequest(xhr: true, supplied: $this->supplied(['xmp' => $this->creatorXmp('Somebody Else')]));
        $media = $this->upload('supplied-loses', $this->creatorXmp('Enrico Romanzi'));

        self::assertSame([['name' => 'Enrico Romanzi', 'type' => 'Person']], $media->getCustomProperty(MediaLicense::CREATOR));
    }

    public function testAnUnusableSidecarLeavesTheDecisionToTheFile(): void
    {
        $this->pushRequest(xhr: true, supplied: 'not json at all');
        $media = $this->upload('supplied-garbage');

        self::assertSame(MediaLicense::STATE_SEEDED, $media->getLicenseState());
    }

    /**
     * The admin form redirects to the index after a create, so whatever the upload
     * decided has to be said out loud — otherwise the site's credit line lands on a
     * media with nothing telling the editor it happened.
     */
    public function testSeedingTellsTheEditorItHappened(): void
    {
        $flashBag = $this->pushRequest();
        $media = $this->upload('disclosed-seed');

        self::assertSame(MediaLicense::STATE_SEEDED, $media->getLicenseState());
        self::assertStringContainsString($this->trans('mediaLicenseSeeded'), $this->flashText($flashBag));
    }

    /** The one that needs a decision: the warning names who the file credits. */
    public function testAThirdPartyUploadWarnsAndNamesTheRightsHolder(): void
    {
        $flashBag = $this->pushRequest();
        $this->upload('disclosed-third-party', $this->creatorXmp('Enrico Romanzi'));

        self::assertStringContainsString('Enrico Romanzi', $this->flashText($flashBag));
    }

    /**
     * Flashes are rendered raw in the admin, and this value comes out of an uploaded
     * file's own metadata.
     */
    public function testAMarkupCarryingCreditIsEscapedInTheWarning(): void
    {
        $flashBag = $this->pushRequest();
        $this->upload('disclosed-injection', $this->creatorXmp('&lt;img src=x onerror=alert(1)&gt;'));

        $text = $this->flashText($flashBag);
        self::assertStringNotContainsString('<img', $text);
        self::assertStringContainsString('&lt;img', $text);
    }

    /** The uploader shows the state on each row; one flash per file would only pile up. */
    public function testABackgroundUploadStaysSilent(): void
    {
        $flashBag = $this->pushRequest(xhr: true);
        $media = $this->upload('disclosed-xhr');

        self::assertSame(MediaLicense::STATE_SEEDED, $media->getLicenseState());
        // Not an empty bag: other listeners have their own say (a duplicate warning
        // when a fixture repeats bytes). Only the license disclosure must be absent.
        self::assertStringNotContainsString($this->trans('mediaLicenseSeeded'), $this->flashText($flashBag));
    }

    /** No seed configured and nothing embedded: nothing happened, nothing to say. */
    public function testAnUndecidedUploadStaysSilent(): void
    {
        $this->configureSeed([]);
        $flashBag = $this->pushRequest();
        $media = $this->upload('disclosed-none');

        self::assertSame(MediaLicense::STATE_NONE, $media->getLicenseState());
        self::assertStringNotContainsString($this->trans('mediaLicenseSeeded'), $this->flashText($flashBag));
    }
}
