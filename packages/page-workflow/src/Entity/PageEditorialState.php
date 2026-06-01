<?php

namespace Pushword\PageWorkflow\Entity;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\PageWorkflow\Repository\PageEditorialStateRepository;

#[ORM\Entity(repositoryClass: PageEditorialStateRepository::class)]
#[ORM\Table(name: 'page_editorial_state')]
class PageEditorialState
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['default' => 'draft'])]
    public string $workflowState = 'draft' {
        set(?string $value) => $this->workflowState = $value ?? 'draft';
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    public ?User $reviewedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $reviewedAt = null;

    public function __construct(
        #[ORM\OneToOne(targetEntity: Page::class)]
        #[ORM\JoinColumn(name: 'page_id', referencedColumnName: 'id', unique: true, nullable: false, onDelete: 'CASCADE')]
        public Page $page
    ) {
    }

    public function getWorkflowState(): string
    {
        return $this->workflowState;
    }

    public function setWorkflowState(?string $workflowState): self
    {
        $this->workflowState = $workflowState;

        return $this;
    }
}
