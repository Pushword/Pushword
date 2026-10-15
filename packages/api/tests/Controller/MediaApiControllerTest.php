<?php

declare(strict_types=1);

namespace Pushword\Api\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Api\Controller\MediaApiController;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\User;
use Pushword\Core\Image\License\MediaLicense;
use Pushword\Core\Tests\Media\Policy\MediaPolicyFixture;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class MediaApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private string $testToken = '';

    private string $testUserEmail = '';

    /** @var list<string> */
    private array $createdMediaFileNames = [];

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $this->testToken = bin2hex(random_bytes(32));
        $this->testUserEmail = 'media-api-test-'.uniqid().'@example.com';
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = $this->testUserEmail;
        $user->setPassword('hashed-password');
        $user->apiToken = $this->testToken;
        $user->setRoles(['ROLE_EDITOR']);

        $this->em->persist($user);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $container = $this->client->getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');
        foreach ($this->createdMediaFileNames as $fileName) {
            $media = $em->getRepository(Media::class)->findOneBy(['fileName' => $fileName]);
            if ($media instanceof Media) {
                $em->remove($media);
            }
        }

        /** @var class-string<User> $userClass */
        $userClass = $container->getParameter('pw.entity_user');
        $user = $em->getRepository($userClass)->findOneBy(['email' => $this->testUserEmail]);
        if (null !== $user) {
            $em->remove($user);
        }

        $em->flush();
        parent::tearDown();
    }

    public function testListFiltersByKeyword(): void
    {
        $marker = uniqid();
        $this->createMedia('zz-needle-'.$marker.'.jpg', 'Some alt text');
        $this->createMedia('zz-other-'.$marker.'.jpg', 'Alt mentioning needle-'.$marker.' keyword');
        $this->createMedia('zz-unrelated-'.$marker.'.jpg', 'Nothing here');

        $response = $this->request('GET', '/api/media?q=needle-'.$marker);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = $this->decode();
        self::assertSame(2, $data['total'], 'q= should match fileName and alt');
        self::assertIsArray($data['items']);
        $filenames = array_column($data['items'], 'filename');
        sort($filenames);
        self::assertSame(['zz-needle-'.$marker.'.jpg', 'zz-other-'.$marker.'.jpg'], $filenames);
    }

    public function testUploadRejectsExecutableContentDisguisedAsAnImage(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pushword-shell-');
        self::assertIsString($path);
        file_put_contents($path, '<?php echo "owned";');

        $this->client->request('POST', '/api/media/shell.png', [], [
            'file' => new UploadedFile($path, 'shell.png', 'image/png', null, true),
        ], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testListAcceptsSearchAsAliasForQ(): void
    {
        $marker = uniqid();
        $this->createMedia('zz-needle-'.$marker.'.jpg', 'Some alt text');
        $this->createMedia('zz-unrelated-'.$marker.'.jpg', 'Nothing here');

        $response = $this->request('GET', '/api/media?search=needle-'.$marker);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = $this->decode();
        self::assertSame(1, $data['total'], 'search= should behave like q=');
        self::assertIsArray($data['items']);
        $item = $data['items'][0];
        self::assertIsArray($item);
        self::assertSame('zz-needle-'.$marker.'.jpg', $item['filename']);
    }

    public function testListKeywordCombinesWithOtherFilters(): void
    {
        $marker = uniqid();
        $this->createMedia('zz-needle-'.$marker.'.jpg', 'Alt', 'image/jpeg');
        $this->createMedia('zz-needle-'.$marker.'.pdf', 'Alt', 'application/pdf');

        $response = $this->request('GET', '/api/media?q=needle-'.$marker.'&mimeType=application/pdf');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = $this->decode();
        self::assertSame(1, $data['total']);
        self::assertIsArray($data['items']);
        $item = $data['items'][0];
        self::assertIsArray($item);
        self::assertSame('zz-needle-'.$marker.'.pdf', $item['filename']);
    }

    public function testRotateSwapsDimensions(): void
    {
        $fileName = 'zz-rotate-'.uniqid().'.jpg';
        $this->createRealImageMedia($fileName, 6, 3);

        $response = $this->requestJson('PATCH', '/api/media/'.$fileName, ['rotate' => 90]);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = $this->decode();
        self::assertIsArray($data['image']);
        self::assertSame(3, $data['image']['width'], 'width and height must be swapped');
        self::assertSame(6, $data['image']['height']);
    }

    public function testRotateRejectsNonMultipleOf90(): void
    {
        $fileName = 'zz-rotate-invalid-'.uniqid().'.jpg';
        $this->createRealImageMedia($fileName, 6, 3);

        $response = $this->requestJson('PATCH', '/api/media/'.$fileName, ['rotate' => 45]);
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRotateRejectsNonImage(): void
    {
        $fileName = 'zz-rotate-'.uniqid().'.pdf';
        $this->createMedia($fileName, 'A document', 'application/pdf');

        $response = $this->requestJson('PATCH', '/api/media/'.$fileName, ['rotate' => 90]);
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * The license keys ride on customProperties, which is a merge-patch: a PATCH on one
     * of them must not wipe the others, and licenseState follows on flush.
     */
    public function testLicenseKeysPatchAndRecomputeTheState(): void
    {
        $fileName = 'zz-license-'.uniqid().'.jpg';
        $media = $this->createMedia($fileName, 'Refuge');
        $media->setCustomProperty(MediaLicense::CREDIT_TEXT, 'ExampleCreditText');
        $media->setCustomProperty(MediaLicense::CREATOR, [['name' => 'ExampleCreator', 'type' => 'Organization']]);

        $this->em->flush();

        $response = $this->requestJson('PATCH', '/api/media/'.$fileName, [
            'customProperties' => [MediaLicense::LICENSE => 'https://example.test/terms'],
        ]);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = $this->decode();
        self::assertIsArray($data['customProperties']);
        self::assertSame('https://example.test/terms', $data['customProperties'][MediaLicense::LICENSE]);
        self::assertSame('ExampleCreditText', $data['customProperties'][MediaLicense::CREDIT_TEXT]);
        self::assertSame(MediaLicense::STATE_OVERRIDDEN, $data['licenseState']);
    }

    /** RFC 7396: a null removes the key. Removing them all stops the media emitting. */
    public function testClearingEveryLicenseKeyLeavesNoLicenseState(): void
    {
        $fileName = 'zz-license-clear-'.uniqid().'.jpg';
        $media = $this->createMedia($fileName, 'Refuge');
        $media->setCustomProperty(MediaLicense::CREDIT_TEXT, 'ExampleCreditText');
        $media->licenseState = MediaLicense::STATE_SEEDED;

        $this->em->flush();

        $response = $this->requestJson('PATCH', '/api/media/'.$fileName, [
            'customProperties' => [MediaLicense::CREDIT_TEXT => null],
        ]);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = $this->decode();
        self::assertIsArray($data['customProperties']);
        self::assertArrayNotHasKey(MediaLicense::CREDIT_TEXT, $data['customProperties']);
        self::assertSame(MediaLicense::STATE_NONE, $data['licenseState']);
    }

    /** licenseState is derived, so the API exposes it but never accepts it. */
    public function testLicenseStateIsReadOnly(): void
    {
        $fileName = 'zz-license-ro-'.uniqid().'.jpg';
        $this->createMedia($fileName, 'Refuge');

        $this->requestJson('PATCH', '/api/media/'.$fileName, ['licenseState' => 'seeded']);

        self::assertSame(MediaLicense::STATE_NONE, $this->decode()['licenseState']);
    }

    // --- An app's media rules (MediaPolicyFixture) ---

    /** The name the API is asked to store the file under is what the rule judges. */
    public function testAnUploadAnAppRuleRefusesIsNotCreated(): void
    {
        $fileName = MediaPolicyFixture::REJECTED.'-'.uniqid().'.jpg';
        $path = sys_get_temp_dir().'/api-policy-'.uniqid().'.jpg';
        $gd = imagecreatetruecolor(3, 2);
        self::assertNotFalse($gd);
        // Random, so the duplicate check never answers in the rule's place.
        imagesetpixel($gd, 0, 0, random_int(1, 0xFFFFFF));
        imagejpeg($gd, $path);

        $this->client->request('POST', '/api/media/'.$fileName, [], [
            'file' => new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true),
        ], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken]);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        $data = $this->decode();
        self::assertSame('validation', $data['error']);
        self::assertIsArray($data['violations']);
        $violation = $data['violations'][0] ?? null;
        self::assertIsArray($violation);
        self::assertSame(MediaPolicyFixture::MESSAGE, $violation['message'] ?? null);
        self::assertNull($this->em->getRepository(Media::class)->findOneBy(['fileName' => $fileName]));
    }

    public function testAMetadataEditAnAppRuleRefusesIsNotSaved(): void
    {
        $fileName = 'zz-policy-'.uniqid().'.jpg';
        $this->createMedia($fileName, 'Before');

        $response = $this->requestJson('PATCH', '/api/media/'.$fileName, ['alt' => MediaPolicyFixture::REJECTED]);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->em->clear();
        self::assertSame('Before', $this->em->getRepository(Media::class)->findOneBy(['fileName' => $fileName])?->getAlt());
    }

    /** Rotation rewrites the file at once, so a refused request must stop before it. */
    public function testARefusedEditLeavesTheImageUnrotated(): void
    {
        $fileName = 'zz-policy-rotate-'.uniqid().'.jpg';
        $media = $this->createRealImageMedia($fileName, 6, 3);

        $response = $this->requestJson('PATCH', '/api/media/'.$fileName, [
            'alt' => MediaPolicyFixture::REJECTED,
            'rotate' => 90,
        ]);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $size = getimagesize($media->getPath());
        self::assertNotFalse($size);
        self::assertSame(6, $size[0]);
    }

    /**
     * The rotation waits for the flush: a rename in the same request moves the file
     * there, and rotating first would read a name not yet on disk.
     */
    public function testRenamingAndRotatingInOneRequest(): void
    {
        $fileName = 'zz-rename-rotate-'.uniqid().'.jpg';
        $renamed = 'zz-renamed-rotated-'.uniqid().'.jpg';
        $this->createRealImageMedia($fileName, 6, 3);
        $this->createdMediaFileNames[] = $renamed;

        $response = $this->requestJson('PATCH', '/api/media/'.$fileName, ['filename' => $renamed, 'rotate' => 90]);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $size = getimagesize(self::getContainer()->getParameter('pw.media_dir').'/'.$renamed);
        self::assertNotFalse($size);
        self::assertSame([3, 6], [$size[0], $size[1]]);
    }

    /**
     * An app may call the controller several times within one request, on one entity
     * manager — upload, re-upload of the same bytes, then a metadata edit — so the
     * last call meets the very instance the upload stored, still holding a file it
     * cannot read. The HTTP client resets Doctrine between requests and would hide it.
     */
    public function testAnUploadThenAnEditInTheSameRequest(): void
    {
        $fileName = 'zz-same-request-'.uniqid().'.png';
        $this->createdMediaFileNames[] = $fileName;
        $controller = self::getContainer()->get(MediaApiController::class);

        $image = imagecreatetruecolor(3, 2);
        self::assertNotFalse($image);
        imagesetpixel($image, 0, 0, random_int(1, 0xFFFFFF));
        $original = sys_get_temp_dir().'/same-request-'.uniqid().'.png';
        imagepng($image, $original);

        foreach ([Response::HTTP_CREATED, Response::HTTP_OK] as $expectedStatus) {
            $copy = $original.'-'.$expectedStatus;
            copy($original, $copy);
            $upload = Request::create('/api/media/'.$fileName, 'POST', files: [
                'file' => new UploadedFile($copy, $fileName, 'image/png', null, true),
            ]);
            self::assertSame($expectedStatus, $controller->item($fileName, $upload)->getStatusCode());
        }

        $edit = Request::create(
            '/api/media/'.$fileName,
            'PATCH',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"alt": "Edited in the same request"}',
        );
        $response = $controller->item($fileName, $edit);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->em->clear();
        self::assertSame('Edited in the same request', $this->em->getRepository(Media::class)->findOneBy(['fileName' => $fileName])?->getAlt());
    }

    public function testADeleteAListenerVetoesKeepsTheMedia(): void
    {
        $fileName = MediaPolicyFixture::LOCKED.'-'.uniqid().'.jpg';
        $this->createMedia($fileName, 'Locked');

        $response = $this->request('DELETE', '/api/media/'.$fileName);

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $data = $this->decode();
        self::assertSame(MediaPolicyFixture::LOCK_REASON, $data['error']);
        self::assertSame([MediaPolicyFixture::LOCK_REASON], $data['reasons']);
        $this->em->clear();
        self::assertNotNull($this->em->getRepository(Media::class)->findOneBy(['fileName' => $fileName]));
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function createRealImageMedia(string $fileName, int $width, int $height): Media
    {
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        $gd = imagecreatetruecolor($width, $height);
        self::assertNotFalse($gd);
        imagejpeg($gd, $mediaDir.'/'.$fileName);

        $hash = sha1_file($mediaDir.'/'.$fileName, true);
        self::assertNotFalse($hash);

        $media = new Media();
        $media->setProjectDir(self::getContainer()->getParameter('kernel.project_dir'))
            ->setStoreIn($mediaDir)
            ->setFileName($fileName)
            ->setAlt('rotation fixture')
            ->setMimeType('image/jpeg')
            ->setDimensions([$width, $height])
            ->setHash($hash);
        $this->em->persist($media);
        $this->em->flush();
        $this->createdMediaFileNames[] = $fileName;

        return $media;
    }

    private function createMedia(string $fileName, string $alt, string $mimeType = 'image/jpeg'): Media
    {
        $media = new Media();
        $media->setProjectDir(self::getContainer()->getParameter('kernel.project_dir'))
            ->setStoreIn(self::getContainer()->getParameter('pw.media_dir'))
            ->setFileName($fileName)
            ->setAlt($alt)
            ->setMimeType($mimeType)
            ->setSize(1)
            ->setHash(sha1($fileName, true));
        $this->em->persist($media);
        $this->em->flush();
        $this->createdMediaFileNames[] = $fileName;

        return $media;
    }

    private function request(string $method, string $url): Response
    {
        $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken];
        $this->client->request($method, $url, [], [], $server);

        return $this->client->getResponse();
    }

    /**
     * @param array<string, mixed> $body
     */
    private function requestJson(string $method, string $url, array $body): Response
    {
        $server = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
            'CONTENT_TYPE' => 'application/json',
        ];
        $this->client->request($method, $url, [], [], $server, (string) json_encode($body));

        return $this->client->getResponse();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        $decoded = json_decode($content, true);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
