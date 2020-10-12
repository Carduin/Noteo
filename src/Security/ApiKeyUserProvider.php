<?php
namespace App\Security;

use App\Repository\EnseignantRepository;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

class ApiKeyUserProvider implements UserProviderInterface
{
    private $enseignantRepo;

    public function __construct(EnseignantRepository $repo)
    {
        $this->enseignantRepo = $repo;
    }

    public function getUsernameForApiKey($apiKey)
    {
        $enseignant = $this->enseignantRepo->findOneByToken($apiKey);
        return $enseignant ? $enseignant->getEmail() : $enseignant;
    }

    public function loadUserByUsername($username)
    {
        $enseignant = $this->enseignantRepo->findOneByEmail($username);

        return $enseignant;
    }

    public function refreshUser(UserInterface $user)
    {
        // this is used for storing authentication in the session
        // but in this example, the token is sent in each request,
        // so authentication can be stateless. Throwing this exception
        // is proper to make things stateless
        throw new UnsupportedUserException();
    }

    public function supportsClass($class)
    {
        return User::class === $class;
    }
}
