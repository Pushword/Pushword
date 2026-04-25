<?php

namespace Pushword\Core\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\User;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class MediaApiControllerTest extends WebTestCase
{
    use PathTrait;

    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private ?User $testUser = null;

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

        $this->ensureMediaFileExists();

        $this->testToken = bin2hex(random_bytes(32));
        $this->testUserEmail = 'media-api-test-'.uniqid().'@example.com';
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $this->testUser = new $userClass();
        $this->testUser->email = $this->testUserEmail;
        $this->testUser->setPassword('hashed-password');
        $this->testUser->apiToken = $this->testToken;

        $this->em->persist($this->testUser);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $container = $this->client->getContainer();
        $this->em = $container->get('doctrine.orm.default_entity_manager');
        $mediaRepo = $this->em->getRepository(Media::class);
        foreach ($this->createdMediaFileNames as $fileName) {
            $media = $mediaRepo->findOneByFileNameOrHistory($fileName);
            if ($media instanceof Media) {
                $this->em->remove($media);
            }
        }
        if ('' !== $this->testUserEmail) {
            /** @var class-string<User> $userClass */
            $userClass = $container->getParameter('pw.entity_user');
            $user = $this->em->getRepository($userClass)->findOneBy(['email' => $this->testUserEmail]);
            if (null !== $user) {
                $this->em->remove($user);
            }
        }
        $this->em->flush();
        $this->createdMediaFileNames = [];
        $this->testUser = null;
        $this->testUserEmail = '';
        parent::tearDown();
    }

    public function testGetRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/media/piedweb-logo.png');
        self::assertResponseStatusCodeSame(401);
    }

    public function testGetWithInvalidToken(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/media/piedweb-logo.png', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer invalid-token',
        ]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testGetReturns404ForUnknownMedia(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/media/nonexistent.png', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testGetReturnsMediaMetadata(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/media/piedweb-logo.png', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ]);

        self::assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        $response = json_decode($content, true);
        self::assertIsArray($response);
        self::assertSame('piedweb-logo.png', $response['filename']);
        self::assertArrayHasKey('mimeType', $response);
        self::assertArrayHasKey('size', $response);
        self::assertArrayHasKey('hash', $response);
        self::assertArrayHasKey('fileNameHistory', $response);
        self::assertIsArray($response['fileNameHistory']);
        self::assertArrayHasKey('customProperties', $response);
        self::assertIsArray($response['customProperties']);
        self::assertArrayHasKey('alt', $response);
        self::assertArrayHasKey('alts', $response);
        self::assertArrayHasKey('tags', $response);
        self::assertArrayHasKey('image', $response);
    }

    public function testPostUpdatesAlt(): void
    {
        $this->client->request(Request::METHOD_POST, '/api/media/piedweb-logo.png', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ], (string) json_encode(['alt' => 'Updated alt text']));

        self::assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        $response = json_decode($content, true);
        self::assertIsArray($response);
        self::assertSame('Updated alt text', $response['alt']);
    }

    public function testPostUpdatesAlts(): void
    {
        $alts = ['fr' => 'Logo en français', 'de' => 'Logo auf Deutsch'];
        $this->client->request(Request::METHOD_POST, '/api/media/piedweb-logo.png', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ], (string) json_encode(['alts' => $alts]));

        self::assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        $response = json_decode($content, true);
        self::assertIsArray($response);
        self::assertSame($alts, $response['alts']);
    }

    public function testPostUpdatesTags(): void
    {
        $tags = ['landscape', 'logo'];
        $this->client->request(Request::METHOD_POST, '/api/media/piedweb-logo.png', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ], (string) json_encode(['tags' => $tags]));

        self::assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        $response = json_decode($content, true);
        self::assertIsArray($response);
        self::assertIsArray($response['tags']);
        self::assertContains('landscape', $response['tags']);
        self::assertContains('logo', $response['tags']);
    }

    public function testPostWithInvalidJsonReturns400(): void
    {
        $this->client->request(Request::METHOD_POST, '/api/media/piedweb-logo.png', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ], 'not-valid-json');

        self::assertResponseStatusCodeSame(400);
    }

    public function testPostJsonReturns404ForUnknownMedia(): void
    {
        $this->client->request(Request::METHOD_POST, '/api/media/nonexistent.png', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ], (string) json_encode(['alt' => 'Does not matter']));

        self::assertResponseStatusCodeSame(404);
    }

    public function testUploadRequiresAuthentication(): void
    {
        $fileName = 'api-upload-'.uniqid().'.jpg';
        $file = new UploadedFile($this->createTempImage($fileName, 0xFF0000, random_int(1, 0xFFFFFF)), $fileName, 'image/jpeg', null, true);

        $this->client->request(Request::METHOD_POST, '/api/media/'.$fileName, [], ['file' => $file]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testUploadCreatesMediaWithMetadata(): void
    {
        $fileName = 'api-upload-'.uniqid().'.jpg';
        $file = new UploadedFile($this->createTempImage($fileName, 0xFF0000, random_int(1, 0xFFFFFF)), $fileName, 'image/jpeg', null, true);

        $this->client->request(
            Request::METHOD_POST,
            '/api/media/'.$fileName,
            [
                'alt' => 'Uploaded via API',
                'alts' => (string) json_encode(['fr' => 'Envoyé via API']),
                'tags' => (string) json_encode(['api', 'test']),
            ],
            ['file' => $file],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken],
        );

        self::assertResponseStatusCodeSame(201);

        $response = $this->decodeResponse();
        self::assertSame($fileName, $response['filename']);
        self::assertSame('Uploaded via API', $response['alt']);
        self::assertSame(['fr' => 'Envoyé via API'], $response['alts']);
        self::assertIsArray($response['tags']);
        self::assertContains('api', $response['tags']);
        self::assertContains('test', $response['tags']);
        self::assertIsArray($response['image']);
        self::assertGreaterThan(0, $response['image']['width']);

        $this->trackCreatedMedia($response['filename']);
    }

    public function testUploadDetectsDuplicateByHash(): void
    {
        $seed = random_int(1, 0xFFFFFF);
        $fileName = 'api-upload-'.uniqid().'.jpg';
        $firstPath = $this->createTempImage($fileName, 0xFF0000, $seed);
        $file = new UploadedFile($firstPath, $fileName, 'image/jpeg', null, true);

        $this->client->request(
            Request::METHOD_POST,
            '/api/media/'.$fileName,
            [],
            ['file' => $file],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken],
        );
        self::assertResponseStatusCodeSame(201);
        $first = $this->decodeResponse();
        self::assertIsString($first['filename']);
        $this->trackCreatedMedia($first['filename']);

        $duplicatePath = $this->createTempImage('dup-'.$fileName, 0xFF0000, $seed);
        self::assertSame(sha1_file($firstPath), sha1_file($duplicatePath));

        $dupFile = new UploadedFile($duplicatePath, 'different-name.jpg', 'image/jpeg', null, true);
        $this->client->request(
            Request::METHOD_POST,
            '/api/media/different-name.jpg',
            [],
            ['file' => $dupFile],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken],
        );

        self::assertResponseIsSuccessful();
        $duplicate = $this->decodeResponse();
        self::assertTrue($duplicate['duplicate'] ?? false);
        self::assertSame($first['filename'], $duplicate['filename']);
        self::assertArrayHasKey('mimeType', $duplicate);
        self::assertArrayHasKey('size', $duplicate);
        self::assertArrayHasKey('alt', $duplicate);
        self::assertArrayHasKey('alts', $duplicate);
        self::assertArrayHasKey('tags', $duplicate);
        self::assertIsArray($duplicate['image']);
    }

    public function testUploadNonImageReturnsNullImageKey(): void
    {
        $fileName = 'api-upload-'.uniqid().'.txt';
        $path = sys_get_temp_dir().'/'.$fileName;
        file_put_contents($path, 'unique content '.uniqid());
        $file = new UploadedFile($path, $fileName, 'text/plain', null, true);

        $this->client->request(
            Request::METHOD_POST,
            '/api/media/'.$fileName,
            [],
            ['file' => $file],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken],
        );

        self::assertResponseStatusCodeSame(201);
        $response = $this->decodeResponse();
        self::assertNull($response['image']);
        self::assertSame('text/plain', $response['mimeType']);

        self::assertIsString($response['filename']);
        $this->trackCreatedMedia($response['filename']);
    }

    public function testUploadAutoRenamesOnFilenameConflict(): void
    {
        $fileName = 'api-rename-'.uniqid().'.jpg';
        $seed1 = random_int(1, 0xFFFFFF);
        $seed2 = $seed1 + 1;

        $file1 = new UploadedFile($this->createTempImage($fileName, 0xFF0000, $seed1), $fileName, 'image/jpeg', null, true);
        $this->client->request(
            Request::METHOD_POST,
            '/api/media/'.$fileName,
            [],
            ['file' => $file1],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken],
        );
        self::assertResponseStatusCodeSame(201);
        $first = $this->decodeResponse();
        self::assertIsString($first['filename']);
        $this->trackCreatedMedia($first['filename']);

        $file2 = new UploadedFile($this->createTempImage('alt-'.$fileName, 0x00FF00, $seed2), $fileName, 'image/jpeg', null, true);
        $this->client->request(
            Request::METHOD_POST,
            '/api/media/'.$fileName,
            [],
            ['file' => $file2],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken],
        );
        self::assertResponseStatusCodeSame(201);
        $second = $this->decodeResponse();
        self::assertIsString($second['filename']);
        $this->trackCreatedMedia($second['filename']);

        self::assertNotSame($first['filename'], $second['filename']);
    }

    public function testPostJsonRenamesMedia(): void
    {
        $original = 'api-to-rename-'.uniqid().'.jpg';
        $renamed = 'api-renamed-'.uniqid().'.jpg';

        $file = new UploadedFile($this->createTempImage($original, 0x0000FF, random_int(1, 0xFFFFFF)), $original, 'image/jpeg', null, true);
        $this->client->request(
            Request::METHOD_POST,
            '/api/media/'.$original,
            [],
            ['file' => $file],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken],
        );
        self::assertResponseStatusCodeSame(201);

        $this->client->request(Request::METHOD_POST, '/api/media/'.$original, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ], (string) json_encode(['filename' => $renamed]));

        self::assertResponseIsSuccessful();
        $response = $this->decodeResponse();
        self::assertSame($renamed, $response['filename']);

        $this->trackCreatedMedia($renamed);
    }

    public function testDeleteRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_DELETE, '/api/media/piedweb-logo.png');
        self::assertResponseStatusCodeSame(401);
    }

    public function testDeleteReturns404ForUnknownMedia(): void
    {
        $this->client->request(Request::METHOD_DELETE, '/api/media/nonexistent.png', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteRemovesMedia(): void
    {
        $fileName = 'api-to-delete-'.uniqid().'.jpg';
        $file = new UploadedFile($this->createTempImage($fileName, 0xFF0000, random_int(1, 0xFFFFFF)), $fileName, 'image/jpeg', null, true);

        $this->client->request(
            Request::METHOD_POST,
            '/api/media/'.$fileName,
            [],
            ['file' => $file],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken],
        );
        self::assertResponseStatusCodeSame(201);
        $created = $this->decodeResponse();
        self::assertIsString($created['filename']);

        $this->client->request(Request::METHOD_DELETE, '/api/media/'.$created['filename'], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ]);
        self::assertResponseStatusCodeSame(204);

        $this->client->request(Request::METHOD_GET, '/api/media/'.$created['filename'], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken,
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    private function createTempImage(string $fileName, int $color = 0xFF0000, ?int $uniqueSeed = null): string
    {
        $path = sys_get_temp_dir().'/'.$fileName;
        $img = imagecreatetruecolor(10, 10);
        $allocated = imagecolorallocate($img, ($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF);
        \assert(false !== $allocated);
        imagefilledrectangle($img, 0, 0, 9, 9, $allocated);

        if (null !== $uniqueSeed) {
            $noise = imagecolorallocate($img, $uniqueSeed & 0xFF, ($uniqueSeed >> 8) & 0xFF, ($uniqueSeed >> 16) & 0xFF);
            \assert(false !== $noise);
            imagesetpixel($img, 0, 0, $noise);
        }

        imagejpeg($img, $path);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        $decoded = json_decode($content, true);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function trackCreatedMedia(string $filename): void
    {
        $this->createdMediaFileNames[] = $filename;
    }
}
