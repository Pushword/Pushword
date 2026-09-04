<?php

namespace Pushword\Quiz\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class QuizResultSigner
{
    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        private string $appSecret,
    ) {
    }

    public function sign(string $host, string $quiz): string
    {
        return hash_hmac('sha256', $host."\0".$quiz, $this->appSecret);
    }

    public function isValid(string $signature, string $host, string $quiz): bool
    {
        return 64 === \strlen($signature) && hash_equals($this->sign($host, $quiz), $signature);
    }
}
