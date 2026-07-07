<?php

namespace Pushword\Quiz\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\SharedTrait\HostTrait;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Pushword\Core\Entity\SharedTrait\IdTrait;
use Pushword\Core\Entity\SharedTrait\TimestampableTrait;
use Pushword\Quiz\Repository\QuizResultRepository;

/**
 * One anonymous quiz attempt per quiz per host. No PII — it feeds only the
 * "better than X% of participants" percentile (knowledge quiz, {@see $score}) or
 * the "X% got the same profile" share (personality test, {@see $result}).
 * Identified leads live in pushword/conversation, not here.
 */
#[ORM\Entity(repositoryClass: QuizResultRepository::class)]
#[ORM\Table(name: 'quiz_result')]
#[ORM\Index(name: 'idx_quiz_result_lookup', columns: ['host', 'quiz'])]
class QuizResult implements IdInterface
{
    use HostTrait;
    use IdTrait;
    use TimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $quiz = '';

    #[ORM\Column(type: Types::SMALLINT)]
    public int $score = 0;

    /**
     * The chosen profile key in a personality test; null for a knowledge quiz.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $result = null;

    public function __construct()
    {
        $this->initTimestampableProperties();
    }
}
