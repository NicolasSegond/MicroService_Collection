<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use PHPUnit\Framework\TestCase;

class ArticleTest extends TestCase
{
    /**
     * Test the constructor of the Article entity
     */
    public function testConstruct(): void
    {
        $article = new Article();

        $this->assertEquals('PUBLISHED', $article->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $article->getCreatedAt());
        $this->assertNull($article->getId(), "Expected id to be null upon construction");
    }

    /**
     * Test all getters and setters of the Article entity
     */
    public function testAttributes(): void
    {
        $article = new Article();
        $title = 'Titre de test optimisé';
        $description = 'Une description sans répétition';
        $price = 199.99;
        $photoUrl = 'https://example.com/photo-hd.jpg';
        $ownerId = 'user-uuid-987654';
        $ownerData = ['id' => 1, 'username' => 'Nicolas'];
        $status = 'SOLD';
        $createdAt = new \DateTimeImmutable('2025-05-20');

        $article->setTitle($title);
        $this->assertSame($title, $article->getTitle());

        $article->setDescription($description);
        $this->assertSame($description, $article->getDescription());

        $article->setPrice($price);
        $this->assertSame($price, $article->getPrice());

        $article->setmainPhotoUrl($photoUrl);
        $this->assertSame($photoUrl, $article->getmainPhotoUrl());

        $article->setOwnerId($ownerId);
        $this->assertSame($ownerId, $article->getOwnerId());

        $article->setOwner($ownerData);
        $this->assertSame($ownerData, $article->getOwner());

        $article->setStatus($status);
        $this->assertSame($status, $article->getStatus());

        $article->setCreatedAt($createdAt);
        $this->assertSame($createdAt, $article->getCreatedAt());
    }
}
