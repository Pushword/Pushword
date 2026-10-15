<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\License\MediaLicense;
use Pushword\Core\Tests\Media\Policy\MediaPolicyFixture;
use Pushword\Core\Utils\FlashBag;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An app's media rules (MediaPolicyFixture, mapped onto Media in the test env) hold on
 * every admin path that writes or removes a media.
 */
#[Group('integration')]
final class MediaPolicyAdminTest extends AbstractAdminTestClass
{
    /** @var list<string> */
    private array $fileNamePrefixes = [];

    /** Straight through Doctrine: no removal path asks the listener there. */
    protected function tearDown(): void
    {
        foreach ($this->fileNamePrefixes as $prefix) {
            while (($media = $this->findByFileNamePrefix($prefix)) instanceof Media) {
                $this->em()->remove($media);
                $this->em()->flush();
            }
        }

        parent::tearDown();
    }

    private function em(): EntityManager
    {
        /** @var EntityManager */
        return self::getContainer()->get('doctrine.orm.entity_manager');
    }

    /** Random pixels, so the SHA-1 duplicate check never answers in the rule's place. */
    private function png(string $name, string $dir = ''): string
    {
        $path = ('' !== $dir ? $dir : sys_get_temp_dir()).'/'.$name;
        $image = imagecreatetruecolor(4, 2);
        \assert(false !== $image);
        imagesetpixel($image, 0, 0, random_int(1, 0xFFFFFF));
        imagesetpixel($image, 3, 1, random_int(1, 0xFFFFFF));
        imagepng($image, $path);

        return $path;
    }

    /** A stored media, its file in the media dir, as an earlier upload left it. */
    private function createMedia(string $fileName, string $alt = 'Before'): Media
    {
        $this->fileNamePrefixes[] = $fileName;
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');
        $path = $this->png($fileName, $mediaDir);
        $hash = sha1_file($path, true);
        \assert(false !== $hash);

        $media = new Media()
            ->setProjectDir(self::getContainer()->getParameter('kernel.project_dir'))
            ->setStoreIn($mediaDir)
            ->setMimeType('image/png')
            ->setDimensions([4, 2])
            ->setSize(1)
            ->setFileName($fileName)
            ->setAlt($alt)
            ->setHash($hash);

        $this->em()->persist($media);
        $this->em()->flush();

        return $media;
    }

    private function reload(Media $media): ?Media
    {
        $this->em()->clear();

        return $this->em()->getRepository(Media::class)->find($media->id);
    }

    private function multiUploadToken(): string
    {
        $crawler = $this->loginUser()->request(Request::METHOD_GET, '/admin/multi-upload');

        return (string) $crawler->filter('#pw-multi-upload')->attr('data-csrf-token');
    }

    /** @return array<string, mixed> */
    private function json(): array
    {
        /** @var array<string, mixed> */
        return json_decode((string) $this->client?->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function flashText(): string
    {
        $flashBag = FlashBag::get($this->client?->getRequest());
        self::assertNotNull($flashBag);

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

    private function findByFileNamePrefix(string $prefix): ?Media
    {
        /** @var Media|null */
        return $this->em()->getRepository(Media::class)->createQueryBuilder('m')
            ->where('m.fileName LIKE :prefix')
            ->setParameter('prefix', $prefix.'%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Submits the new-media form with a file and the raw license payload the browser
     * posts — the creator collection is compound, which DomCrawler cannot assign to.
     *
     * @param array<string, string> $query
     * @param array<string, mixed>  $fields
     */
    private function submitNewMedia(string $filePath, array $fields = [], array $query = []): Crawler
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $router = self::getContainer()->get('router');
        $crawler = $client->request(Request::METHOD_GET, $router->generate('admin_media_new', $query));
        $form = $crawler->filter('form[name="Media"]')->form();

        $fileField = $form['Media[mediaFile]'];
        self::assertInstanceOf(FileFormField::class, $fileField);
        $fileField->upload($filePath);

        $values = $form->getPhpValues();
        /** @var array<string, mixed> $media */
        $media = $values['Media'] ?? [];
        $values['Media'] = [...$media, ...$fields];

        return $client->request($form->getMethod(), $form->getUri(), $values, $form->getPhpFiles());
    }

    private function assertRefusedWithARootError(Crawler $crawler): void
    {
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client?->getResponse()->getStatusCode());
        // A class constraint has no field to sit under: EasyAdmin shows it above the form.
        self::assertStringContainsString(
            MediaPolicyFixture::MESSAGE,
            $crawler->filter('.global-invalid-feedback')->text(''),
        );
    }

    // --- Admin form ---

    /**
     * The license block is unmapped and used to be written back only after the save
     * was decided, so a rule requiring a credit could never see the one typed.
     */
    public function testAnAppRuleSeesTheCreditTypedInTheForm(): void
    {
        $prefix = 'policy-credit-'.uniqid();
        $this->fileNamePrefixes[] = $prefix;
        $crawler = $this->submitNewMedia($this->png($prefix.'.png'), [
            MediaLicense::CREDIT_TEXT => MediaPolicyFixture::REJECTED,
        ]);

        $this->assertRefusedWithARootError($crawler);
        self::assertNull($this->findByFileNamePrefix($prefix));
    }

    /**
     * Written back before validation, the values must still survive it — the
     * free-form YAML textarea merges during validation — and outrank the file's.
     */
    public function testTheCreditTypedWithANewUploadIsSaved(): void
    {
        $prefix = 'policy-typed-credit-'.uniqid();
        $this->fileNamePrefixes[] = $prefix;

        $this->submitNewMedia($this->png($prefix.'.png'), [MediaLicense::CREDIT_TEXT => 'Typed credit']);

        self::assertTrue($this->client?->getResponse()->isRedirect());
        $media = $this->findByFileNamePrefix($prefix);
        self::assertInstanceOf(Media::class, $media);
        self::assertSame('Typed credit', $media->getCustomPropertyScalar(MediaLicense::CREDIT_TEXT));
        self::assertSame(MediaLicense::STATE_OVERRIDDEN, $media->licenseState);
    }

    /** The name is otherwise only decided at prePersist, after validation. */
    public function testAnAppRuleSeesTheNameANewUploadIsAboutToGet(): void
    {
        $prefix = MediaPolicyFixture::REJECTED.'-'.uniqid();
        $this->fileNamePrefixes[] = $prefix;
        $crawler = $this->submitNewMedia($this->png($prefix.'.png'));

        $this->assertRefusedWithARootError($crawler);
        self::assertNull($this->findByFileNamePrefix($prefix));
    }

    /** "Upload" in a media picker opens this same form inside a modal. */
    public function testTheRuleIsShownInsideTheMediaPickerModal(): void
    {
        $prefix = MediaPolicyFixture::REJECTED.'-picker-'.uniqid();
        $this->fileNamePrefixes[] = $prefix;
        $crawler = $this->submitNewMedia($this->png($prefix.'.png'), query: ['pwMediaPicker' => '1']);

        $this->assertRefusedWithARootError($crawler);
    }

    // --- Multi-upload and inline editing ---

    public function testAMultiUploadAnAppRuleRefusesIsNotCreated(): void
    {
        $token = $this->multiUploadToken();
        $prefix = MediaPolicyFixture::REJECTED.'-multi-'.uniqid();
        $this->fileNamePrefixes[] = $prefix;
        $path = $this->png($prefix.'.png');

        $this->client?->request(Request::METHOD_POST, '/admin/multi-upload/upload', ['_token' => $token], [
            'file' => new UploadedFile($path, $prefix.'.png', 'image/png', null, true),
        ]);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client?->getResponse()->getStatusCode());
        self::assertSame(MediaPolicyFixture::MESSAGE, $this->json()['error']);
        self::assertNull($this->findByFileNamePrefix($prefix));
    }

    /** An exact duplicate writes nothing, so there is nothing for a rule to refuse. */
    public function testAnExactDuplicateIsSkippedBeforeAnyRuleIsAsked(): void
    {
        $existing = $this->createMedia('policy-duplicate-'.uniqid().'.png');
        $token = $this->multiUploadToken();

        $copy = sys_get_temp_dir().'/'.MediaPolicyFixture::REJECTED.'-'.uniqid().'.png';
        copy($existing->getPath(), $copy);

        $this->client?->request(Request::METHOD_POST, '/admin/multi-upload/upload', ['_token' => $token], [
            'file' => new UploadedFile($copy, basename($copy), 'image/png', null, true),
        ]);

        self::assertSame(Response::HTTP_OK, $this->client?->getResponse()->getStatusCode());
        $data = $this->json();
        self::assertTrue($data['skipped'] ?? false);
        self::assertSame($existing->id, $data['id']);
    }

    public function testAnInlineEditAnAppRuleRefusesIsNotSaved(): void
    {
        $media = $this->createMedia('policy-inline-'.uniqid().'.png');
        $token = $this->multiUploadToken();

        $this->client?->request(Request::METHOD_POST, '/admin/media/'.$media->id.'/inline-update', [
            '_token' => $token,
            'field' => 'alt',
            'value' => MediaPolicyFixture::REJECTED,
        ]);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client?->getResponse()->getStatusCode());
        self::assertSame(MediaPolicyFixture::MESSAGE, $this->json()['error']);
        self::assertSame('Before', $this->reload($media)?->getAlt());
    }

    /** Media's own rules too, which only the admin form used to check — and translated. */
    public function testAnInlineEditBreakingMediasOwnRulesIsNotSaved(): void
    {
        $media = $this->createMedia('policy-inline-alts-'.uniqid().'.png');
        $token = $this->multiUploadToken();

        $this->client?->request(Request::METHOD_POST, '/admin/media/'.$media->id.'/inline-update', [
            '_token' => $token,
            'field' => 'alts',
            'value' => 'fr: [unclosed',
        ]);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client?->getResponse()->getStatusCode());
        $error = $this->json()['error'];
        self::assertIsString($error);
        self::assertStringContainsString('YAML', $error, 'the violation reached the editor untranslated');
        self::assertSame('', $this->reload($media)?->getAlts());
    }

    // --- Removal ---

    public function testAMultiDeleteAListenerVetoesKeepsTheMedia(): void
    {
        $media = $this->createMedia(MediaPolicyFixture::LOCKED.'-multi-'.uniqid().'.png');
        $token = $this->multiUploadToken();

        $this->client?->request(Request::METHOD_POST, '/admin/media/'.$media->id.'/multi-delete', ['_token' => $token]);

        self::assertSame(Response::HTTP_CONFLICT, $this->client?->getResponse()->getStatusCode());
        $data = $this->json();
        self::assertSame(MediaPolicyFixture::LOCK_REASON, $data['error']);
        self::assertSame([MediaPolicyFixture::LOCK_REASON], $data['reasons']);
        self::assertNotNull($this->reload($media));
    }

    public function testTheAdminDeleteKeepsAVetoedMediaAndSaysWhy(): void
    {
        $media = $this->createMedia(MediaPolicyFixture::LOCKED.'-crud-'.uniqid().'.png');
        $client = $this->loginUser();
        $client->request(Request::METHOD_GET, '/admin/media');

        $client->request(Request::METHOD_POST, '/admin/media/'.$media->id.'/delete', [
            'token' => $this->csrfToken($client, 'ea-delete'),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());
        self::assertStringContainsString(htmlspecialchars(MediaPolicyFixture::LOCK_REASON), $this->flashText());
        self::assertStringNotContainsString('<b>', $this->flashText(), 'flashes render raw: the reason must be escaped');
        self::assertStringContainsString($media->getFileName(), $this->flashText());
        self::assertNotNull($this->reload($media));
    }

    /** One veto keeps one media: the rest of the batch still goes. */
    public function testABatchDeleteKeepsOnlyTheVetoedMedia(): void
    {
        $locked = $this->createMedia(MediaPolicyFixture::LOCKED.'-batch-'.uniqid().'.png');
        $free = $this->createMedia('policy-batch-free-'.uniqid().'.png');
        $client = $this->loginUser();
        $client->request(Request::METHOD_GET, '/admin/media');

        $client->request(Request::METHOD_POST, '/admin/media/batch-delete', [
            'batchActionName' => 'batchDelete',
            'entityFqcn' => Media::class,
            'batchActionEntityIds' => [(string) $locked->id, (string) $free->id],
            'batchActionCsrfToken' => $this->csrfToken($client, 'ea-batch-action-batchDelete-'.Media::class),
        ]);

        self::assertTrue($client->getResponse()->isRedirect());
        self::assertStringContainsString(htmlspecialchars(MediaPolicyFixture::LOCK_REASON), $this->flashText());
        self::assertNotNull($this->reload($locked));
        self::assertNull($this->reload($free));
    }
}
