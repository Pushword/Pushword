<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Sms;

use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class SmsGateClient
{
    public function __construct(private HttpClientInterface $httpClient, private SmsSettings $settings)
    {
    }

    public function submit(string $reference, string $phone, string $text): string
    {
        if (! $this->settings->canSend()) {
            throw new RuntimeException('SMS sending is disabled.');
        }

        if (1 !== preg_match('/^\+[1-9][0-9]{7,14}$/D', $phone) || '' === trim($text) || mb_strlen($text) > 480) {
            throw new RuntimeException('Invalid SMS recipient or content.');
        }

        $state = $this->status($reference);
        if (null !== $state) {
            return $state;
        }

        $response = $this->request('POST', '/messages', [
            'id' => $reference,
            'deviceId' => $this->settings->deviceId,
            'simNumber' => $this->settings->simNumber,
            'phoneNumbers' => [$phone],
            'textMessage' => ['text' => $text],
            'ttl' => 3600,
            'withDeliveryReport' => true,
        ]);

        return 409 === $response->getStatusCode()
            ? ($this->status($reference) ?? throw new RuntimeException('Invalid SMSGate response.'))
            : $this->state($response);
    }

    public function status(string $reference): ?string
    {
        if (! $this->settings->isConfigured() || 1 !== preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $reference)) {
            throw new RuntimeException('Invalid SMSGate configuration or message reference.');
        }

        $response = $this->request('GET', '/messages/'.$reference);

        return 404 === $response->getStatusCode() ? null : $this->state($response);
    }

    /** @param array<string, mixed>|null $json */
    private function request(string $method, string $path, ?array $json = null): ResponseInterface
    {
        $options = [
            'auth_basic' => [$this->settings->username, $this->settings->password],
            'max_redirects' => 0,
            'timeout' => 20,
            'max_duration' => 25,
        ];
        if (null !== $json) {
            $options['json'] = $json;
        }

        return $this->httpClient->request($method, rtrim($this->settings->url, '/').$path, $options);
    }

    private function state(ResponseInterface $response): string
    {
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new RuntimeException('SMSGate request failed.');
        }

        $state = $response->toArray(false)['state'] ?? null;
        if (! \is_string($state) || ! \in_array($state, ['Pending', 'Processed', 'Sent', 'Delivered', 'Failed', 'Cancelling', 'Cancelled'], true)) {
            throw new RuntimeException('Invalid SMSGate response.');
        }

        return $state;
    }
}
