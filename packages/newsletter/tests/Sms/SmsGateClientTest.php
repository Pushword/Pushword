<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Sms;

use PHPUnit\Framework\TestCase;
use Pushword\Newsletter\Sms\SmsGateClient;
use Pushword\Newsletter\Sms\SmsSettings;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SmsGateClientTest extends TestCase
{
    public function testDeviceSimAndStableIdentityAreSentWithoutFollowingRedirects(): void
    {
        $calls = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$calls): MockResponse {
            $calls[] = [$method, $url, $options];

            return new MockResponse('GET' === $method ? '{}' : '{"state":"Pending"}', ['http_code' => 'GET' === $method ? 404 : 201]);
        });
        self::assertSame('Pending', new SmsGateClient($http, $this->settings())->submit('stable-id', '+33612345678', 'Hello'));
        self::assertCount(2, $calls);
        self::assertSame('GET', $calls[0][0]);
        self::assertStringEndsWith('/messages/stable-id', $calls[0][1]);
        self::assertSame('POST', $calls[1][0]);
        self::assertSame(0, $calls[1][2]['max_redirects']);
        self::assertIsString($calls[1][2]['body']);
        $body = json_decode($calls[1][2]['body'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('stable-id', $body['id']);
        self::assertSame('test-device', $body['deviceId']);
        self::assertSame(2, $body['simNumber']);
        self::assertSame(['+33612345678'], $body['phoneNumbers']);
        self::assertSame(['text' => 'Hello'], $body['textMessage']);
        self::assertTrue($body['withDeliveryReport']);
    }

    public function testAnExistingIdentityIsNeverPostedAgain(): void
    {
        $http = new MockHttpClient([new MockResponse('{"state":"Delivered"}')]);
        self::assertSame('Delivered', new SmsGateClient($http, $this->settings())->submit('stable-id', '+33612345678', 'Hello'));
        self::assertSame(1, $http->getRequestsCount());
    }

    public function testAConcurrentSubmissionConflictReadsTheExistingIdentity(): void
    {
        $http = new MockHttpClient([
            new MockResponse('{}', ['http_code' => 404]),
            new MockResponse('{}', ['http_code' => 409]),
            new MockResponse('{"state":"Sent"}'),
        ]);
        self::assertSame('Sent', new SmsGateClient($http, $this->settings())->submit('stable-id', '+33612345678', 'Hello'));
        self::assertSame(3, $http->getRequestsCount());
    }

    public function testDisabledSendingNeverCallsHttp(): void
    {
        $http = new MockHttpClient([new MockResponse('{"state":"Pending"}')]);

        try {
            new SmsGateClient($http, $this->settings(false))->submit('stable-id', '+33612345678', 'Hello');
            self::fail('Disabled sending must be refused.');
        } catch (RuntimeException) {
            self::assertSame(0, $http->getRequestsCount());
        }
    }

    public function testInvalidPayloadNeverCallsHttp(): void
    {
        $http = new MockHttpClient();
        foreach ([['0612345678', 'Hello'], ['+33612345678', ''], ['+33612345678', str_repeat('a', 481)]] as [$phone, $text]) {
            try {
                new SmsGateClient($http, $this->settings())->submit('stable-id', $phone, $text);
                self::fail('Invalid SMS must be refused.');
            } catch (RuntimeException) {
                self::assertSame(0, $http->getRequestsCount());
            }
        }
    }

    public function testProviderErrorsNeverExposeResponseBodies(): void
    {
        $http = new MockHttpClient([new MockResponse('secret provider body', ['http_code' => 500])]);
        $this->expectExceptionMessageIsOrContains('SMSGate request failed.');
        new SmsGateClient($http, $this->settings())->status('stable-id');
    }

    public function testIncompleteOrInsecureConfigurationKeepsSmsHidden(): void
    {
        self::assertFalse($this->settings(url: 'http://sms.example.test/api')->isConfigured());
        self::assertFalse($this->settings(url: 'https://')->isConfigured());
        self::assertTrue($this->settings(url: 'http://127.0.0.1:3000/api')->isConfigured());
        self::assertTrue($this->settings(false)->isConfigured());
        self::assertFalse($this->settings(false)->canSend());
    }

    private function settings(bool $enabled = true, string $url = 'https://sms.example.test/api'): SmsSettings
    {
        return new SmsSettings($url, 'test-user', 'test-password', 'test-device', 2, $enabled, 24, 30, 0, 24, 'UTC');
    }
}
