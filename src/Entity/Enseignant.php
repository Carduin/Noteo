<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass="App\Repository\EnseignantRepository")
 * @UniqueEntity(fields={"email"}, message="Cette adresse email existe déjà !")
 */
class Enseignant implements UserInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Assert\Length(max=255)
     * @Assert\NotBlank
     * @Assert\Email
     */
    private $email;

    /**
     * @ORM\Column(type="json")
     */
    private $roles = [];

    /**
     * @var string The hashed password
     * @ORM\Column(type="string")
     * @Assert\NotBlank
     */
    private $password;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\Length(max=255)
     * @Assert\NotBlank
     */
    private $nom;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\Length(max=255)
     * @Assert\NotBlank
     */
    private $prenom;

    /**
     * @ORM\Column(type="integer")
     * @Assert\NotBlank
     * @Assert\GreaterThanOrEqual(-1)
     * @Assert\NotEqualTo(0)
     */
    private $preferenceNbElementsTableaux;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\GroupeEtudiant", mappedBy="enseignant")
     */
    private $groupes;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Evaluation", mappedBy="enseignant")
     */
    private $evaluations;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Statut", mappedBy="enseignant")
     */
    private $statuts;

    public function __construct()
    {
        $this->groupes = new ArrayCollection();
        $this->evaluations = new ArrayCollection();
        $this->statuts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = strtolower(trim($email));
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function isAdmin()
    {
        return in_array('ROLE_ADMIN', $this->getRoles(), true);
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getSalt()
    {
        // not needed when using the "bcrypt" algorithm in security.yaml
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        setlocale(LC_TIME, "fr_FR"); // Pour que les carctères è é à soient bien mis en majuscule
        $leNom = mb_strtoupper(trim($nom)); //Met en majuscules en fonction de la locale
        $this->nom = $leNom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): self
    {
        setlocale(LC_TIME, "fr_FR"); // Pour que les carctères è é à soient bien mis en minuscule
        $lePrenom = ucwords(mb_strtolower(trim($prenom)), "- '");
        $this->prenom = $lePrenom;
        return $this;
    }

    /**
     * @return Collection|GroupeEtudiant[]
     */
    public function getGroupes(): Collection
    {
        return $this->groupes;
    }

    public function addGroupe(GroupeEtudiant $groupe): self
    {
        if (!$this->groupes->contains($groupe)) {
            $this->groupes[] = $groupe;
            $groupe->setEnseignant($this);
        }

        return $this;
    }

    public function removeGroupe(GroupeEtudiant $groupe): self
    {
        if ($this->groupes->contains($groupe)) {
            $this->groupes->removeElement($groupe);
            // set the owning side to null (unless already changed)
            if ($groupe->getEnseignant() === $this) {
                $groupe->setEnseignant(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Evaluation[]
     */
    public function getEvaluations(): Collection
    {
        return $this->evaluations;
    }

    public function addEvaluation(Evaluation $evaluation): self
    {
        if (!$this->evaluations->contains($evaluation)) {
            $this->evaluations[] = $evaluation;
            $evaluation->setEnseignant($this);
        }

        return $this;
    }

    public function removeEvaluation(Evaluation $evaluation): self
    {
        if ($this->evaluations->contains($evaluation)) {
            $this->evaluations->removeElement($evaluation);
            // set the owning side to null (unless already changed)
            if ($evaluation->getEnseignant() === $this) {
                $evaluation->setEnseignant(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Statut[]
     */
    public function getStatuts(): Collection
    {
        return $this->statuts;
    }

    public function addStatut(Statut $statut): self
    {
        if (!$this->statuts->contains($statut)) {
            $this->statuts[] = $statut;
            $statut->setEnseignant($this);
        }

        return $this;
    }

    public function removeStatut(Statut $statut): self
    {
        if ($this->statuts->contains($statut)) {
            $this->statuts->removeElement($statut);
            // set the owning side to null (unless already changed)
            if ($statut->getEnseignant() === $this) {
                $statut->setEnseignant(null);
            }
        }

        return $this;
    }

    public function getPreferenceNbElementsTableaux(): ?int
    {
        return $this->preferenceNbElementsTableaux;
    }

    public function setPreferenceNbElementsTableaux(int $preferenceNbElementsTableaux): self
    {
        $this->preferenceNbElementsTableaux = $preferenceNbElementsTableaux;

        return $this;
    }
}
