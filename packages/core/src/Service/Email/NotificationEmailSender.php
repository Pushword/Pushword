<?php

declare(strict_types=1);

namespace Pushword\Core\Service\Email;

use Psr\Log\LoggerInterface;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Throwable;
use Twig\Environment;

/**
 * Unified service for sending notification emails across all Pushword packages.
 *
 * Supports config fallback chain:
 * 1. Package-specific keys (e.g., conversation_notification_email_from)
 * 2. Global defaults (notification_email_from, notification_email_to)
 * 3. Auto-generated fallback (noreply@{host})
 */
final readonly class NotificationEmailSender
{
    public function __construct(
        private ?MailerInterface $mailer,
        private AppPool $apps,
        private ?Environment $twig,
        private ?LoggerInterface $logger,
    ) {
    }

    /**
     * Resolve email envelope using config fallback chain.
     *
     * @param string|null          $fromConfigKey Package-specific from key (e.g., 'conversation_notification_email_from')
     * @param string|string[]|null $toConfigKey   Package-specific to key (e.g., 'conversation_notification_email_to') or direct recipients array
     * @param string|null          $host          Optional host to use instead of current app
     */
    public function resolveEnvelope(
        ?string $fromConfigKey = null,
        string|array|null $toConfigKey = null,
        ?string $host = null,
    ): EmailEnvelope {
        $app = $this->apps->get($host);

        $from = $this->resolveFromAddress($app, $fromConfigKey);
        $to = $this->resolveToAddresses($app, $toConfigKey);

        return new EmailEnvelope($from, $to);
    }

    /**
     * Send a plain text/HTML email.
     */
    public function send(
        EmailEnvelope $envelope,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
    ): bool {
        if (! $this->canSend($envelope)) {
            return false;
        }

        try {
            $email = new Email()
                ->from($envelope->from)
                ->to(...$envelope->to)
                ->subject($subject)
                ->html($htmlBody);

            if (null !== $textBody) {
                $email->text($textBody);
            }

            if (null !== $envelope->replyTo) {
                $email->replyTo($envelope->replyTo);
            }

            $this->mailer?->send($email);

            $this->logger?->info('[NotificationEmailSender] Email sent', [
                'subject' => $subject,
                'to' => $envelope->to,
            ]);

            return true;
        } catch (Throwable $throwable) {
            $this->logger?->error('[NotificationEmailSender] Failed to send email', [
                'error' => $throwable->getMessage(),
                'subject' => $subject,
            ]);

            return false;
        }
    }

    /**
     * Send a templated email using Symfony's TemplatedEmail.
     *
     * @param array<string, mixed> $context
     */
    public function sendTemplated(
        EmailEnvelope $envelope,
        string $subject,
        string $template,
        array $context = [],
    ): bool {
        if (! $this->canSend($envelope)) {
            return false;
        }

        try {
            $email = new TemplatedEmail()
                ->from($envelope->from)
                ->to(...$envelope->to)
                ->subject($subject)
                ->htmlTemplate($template)
                ->context($context);

            if (null !== $envelope->replyTo) {
                $email->replyTo($envelope->replyTo);
            }

            $this->mailer?->send($email);

            $this->logger?->info('[NotificationEmailSender] Templated email sent', [
                'subject' => $subject,
                'template' => $template,
                'to' => $envelope->to,
            ]);

            return true;
        } catch (Throwable $throwable) {
            $this->logger?->error('[NotificationEmailSender] Failed to send templated email', [
                'error' => $throwable->getMessage(),
                'subject' => $subject,
                'template' => $template,
            ]);

            return false;
        }
    }

    /**
     * Send email with HTML rendered from a Twig template (for services using plain Email).
     *
     * @param array<string, mixed> $context
     */
    public function sendWithRenderedHtml(
        EmailEnvelope $envelope,
        string $subject,
        string $template,
        array $context = [],
        ?string $fallbackHtml = null,
    ): bool {
        $html = $this->renderTemplate($template, $context, $fallbackHtml);

        if (null === $html) {
            $this->logger?->warning('[NotificationEmailSender] Could not render template', [
                'template' => $template,
            ]);

            return false;
        }

        return $this->send($envelope, $subject, $html);
    }

    /**
     * Check if email sending is possible.
     */
    public function canSend(EmailEnvelope $envelope): bool
    {
        if (null === $this->mailer) {
            $this->logger?->debug('[NotificationEmailSender] Mailer not available');

            return false;
        }

        if (! $envelope->isValid()) {
            $this->logger?->info('[NotificationEmailSender] Invalid email envelope', [
                'from' => $envelope->from,
                'to' => $envelope->to,
            ]);

            return false;
        }

        return true;
    }

    private function resolveFromAddress(AppConfig $app, ?string $configKey): string
    {
        // 1. Try package-specific key
        if (null !== $configKey) {
            $value = $app->getStr($configKey);
            if ('' !== $value) {
                return $value;
            }
        }

        // 2. Try global default
        $globalFrom = $app->getStr('notification_email_from');
        if ('' !== $globalFrom) {
            return $globalFrom;
        }

        // 3. Auto-generate fallback
        return 'noreply@'.$app->getMainHost();
    }

    /**
     * @param string|string[]|null $configKey
     *
     * @return string[]
     */
    private function resolveToAddresses(AppConfig $app, string|array|null $configKey): array
    {
        // Handle array of addresses directly (for direct recipients like user email)
        if (\is_array($configKey)) {
            return array_filter($configKey, static fn (string $email): bool => '' !== $email);
        }

        // 1. Try package-specific key
        if (null !== $configKey && '' !== $configKey) {
            $value = $app->get($configKey);
            if (\is_array($value)) {
                return array_filter($value, static fn (mixed $v): bool => \is_string($v) && '' !== $v);
            }

            if (\is_string($value) && '' !== $value) {
                return [$value];
            }
        }

        // 2. Try global default
        $globalTo = $app->get('notification_email_to');
        if (\is_array($globalTo)) {
            return array_filter($globalTo, static fn (mixed $v): bool => \is_string($v) && '' !== $v);
        }

        if (\is_string($globalTo) && '' !== $globalTo) {
            return [$globalTo];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderTemplate(string $template, array $context, ?string $fallback): ?string
    {
        if (null === $this->twig) {
            return $fallback;
        }

        try {
            return $this->twig->render($template, $context);
        } catch (Throwable) {
            return $fallback;
        }
    }
}
