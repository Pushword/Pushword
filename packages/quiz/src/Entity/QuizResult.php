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
 * One anonymous quiz attempt: just a score (in %) per quiz per host. No PII —
 * this feeds the "better than X% of participants" percentile only. Identified
 * leads live in pushword/conversation, not here.
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

    public function __construct()
    {
        $this->initTimestampableProperties();
    }
}
