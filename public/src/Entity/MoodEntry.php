<?php

namespace App\Entity;

use App\Repository\MoodEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MoodEntryRepository::class)]
#[ORM\Table(name: 'mood_entry')]
#[ORM\Index(columns: ['user_id', 'created_at'], name: 'idx_mood_entry_user_created')]
#[ORM\HasLifecycleCallbacks]
class MoodEntry
{
    /**
     * Life aspects rated in a check-in.
     * Mental health is captured by overallMood (+ optional overall note), not as a separate key.
     */
    public const ASPECT_KEYS = [
        'close_relationships',
        'romantic_relationships',
        'duties',
        'physical_health',
        'finances',
        'relaxation',
        'growth_spirituality',
        'environment',
    ];

    public const ASPECT_NOTE_MIN_LENGTH = 5;
    public const ASPECT_NOTE_MAX_LENGTH = 500;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['mood:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Groups(['mood:read'])]
    private int $overallMood = 0;

    /**
     * @var array<string, array{score: int, note: ?string}>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Groups(['mood:read'])]
    private array $aspects = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['mood:read'])]
    private ?string $note = null;

    #[ORM\Column]
    #[Groups(['mood:read'])]
    private int $xpEarned = 0;

    #[ORM\Column]
    #[Groups(['mood:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['mood:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getOverallMood(): int
    {
        return $this->overallMood;
    }

    public function setOverallMood(int $overallMood): static
    {
        $this->overallMood = $overallMood;

        return $this;
    }

    /**
     * @return array<string, array{score: int, note: ?string}>
     */
    public function getAspects(): array
    {
        return $this->aspects;
    }

    /**
     * @param array<string, array{score: int, note: ?string}> $aspects
     */
    public function setAspects(array $aspects): static
    {
        $this->aspects = $aspects;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getXpEarned(): int
    {
        return $this->xpEarned;
    }

    public function setXpEarned(int $xpEarned): static
    {
        $this->xpEarned = $xpEarned;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAt(): static
    {
        $this->createdAt = new \DateTimeImmutable();

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function setUpdatedAt(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
