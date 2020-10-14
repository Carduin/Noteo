<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ShortenedURLRepository")
 */
class ShortenedURL
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $urlToken;

    /**
     * @ORM\Column(type="json")
     */
    private $UrlParameters;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrlToken(): ?string
    {
        return $this->urlToken;
    }

    public function generateUrlToken(): self
    {
        $this->urlToken = rtrim(strtr(base64_encode(random_bytes(8)), '+/', '-_'), '=');

        return $this;
    }

    public function getUrlParameters(): ?array
    {
        return $this->UrlParameters;
    }

    public function setUrlParameters(array $UrlParameters): self
    {
        $this->UrlParameters = $UrlParameters;

        return $this;
    }
}
