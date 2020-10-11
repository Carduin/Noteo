<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ApiLogRepository")
 */
class ApiLog
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
    private $type;

    /**
     * @ORM\Column(type="datetime")
     */
    private $calledAt;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Enseignant")
     * @ORM\JoinColumn(nullable=false)
     */
    private $enseignant;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getCalledAt(): ?\DateTimeInterface
    {
        return $this->calledAt;
    }

    public function setCalledAt(\DateTimeInterface $date): self
    {
        $this->calledAt = $date;

        return $this;
    }

    public function getEnseignant(): ?Enseignant
    {
        return $this->enseignant;
    }

    public function setEnseignant(?Enseignant $enseignant): self
    {
        $this->enseignant = $enseignant;

        return $this;
    }
}
