<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Filter\ForcePublishedFilter;
use App\Repository\ArticleRepository;
use App\State\ArticleCreationProcessor;
use App\State\ArticleWithOwnerProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

// Ajout de l'import

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            paginationEnabled: true,
            paginationItemsPerPage: 10,
            normalizationContext: ['groups' => ['article:read']],
            filters: [ForcePublishedFilter::class],
            provider: ArticleWithOwnerProvider::class
        ),
        new GetCollection(
            uriTemplate: '/admin/articles',
            paginationEnabled: true,
            paginationItemsPerPage: 20,
            normalizationContext: ['groups' => ['article:read']],
            provider: ArticleWithOwnerProvider::class
        ),
        new Get(
            paginationEnabled: true,
            paginationItemsPerPage: 10,
            normalizationContext: ['groups' => ['article:read']],
            filters: [ForcePublishedFilter::class],
            provider: ArticleWithOwnerProvider::class
        ),
        new Post(
            normalizationContext: ['groups' => ['article:read']],
            denormalizationContext: ['groups' => ['article:write']],
            processor: ArticleCreationProcessor::class
        ),
        new Patch(
            normalizationContext: ['groups' => ['article:read']],
            denormalizationContext: ['groups' => ['article:write']],
        )
    ]
)]
#[ApiFilter(SearchFilter::class, properties: ['title' => 'ipartial'])]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['article:read'])]
    private ?int $id = null;
    #[ORM\Column(length: 255)]
    #[Groups(['article:read', 'article:write'])]
    private ?string $title = null;
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['article:read', 'article:write'])]
    private ?string $description = null;
    #[ORM\Column]
    #[Groups(['article:read', 'article:write'])]
    private ?float $price = null;
    #[ORM\Column(nullable: true)]
    #[Groups(['article:read', 'article:write'])]
    private ?float $shippingCost = null;
    #[ORM\Column(length: 255)]
    #[Groups(['article:read', 'article:write'])]
    private ?string $mainPhotoUrl = null;
    #[ORM\Column(length: 255)]
    #[Groups(['article:read'])]
    private ?string $ownerId = null;
    #[Groups(['article:read'])]
    #[ApiProperty(jsonSchemaContext: ['type' => ['object', 'null']])]
    private ?array $owner = null;
    #[ORM\Column(length: 50)]
    #[Groups(['article:read', 'article:write'])]
    private ?string $status = null;
    #[ORM\Column]
    #[Groups(['article:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = 'DRAFT';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getShippingCost(): ?float
    {
        return $this->shippingCost;
    }

    public function setShippingCost(?float $shippingCost): static
    {
        $this->shippingCost = $shippingCost;

        return $this;
    }

    public function getmainPhotoUrl(): ?string
    {
        return $this->mainPhotoUrl;
    }

    public function setmainPhotoUrl(string $mainPhotoUrl): static
    {
        $this->mainPhotoUrl = $mainPhotoUrl;

        return $this;
    }

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(string $ownerId): self
    {
        $this->ownerId = $ownerId;
        return $this;
    }

    public function getOwner(): ?array
    {
        return $this->owner;
    }

    public function setOwner(array $owner): void
    {
        $this->owner = $owner;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
