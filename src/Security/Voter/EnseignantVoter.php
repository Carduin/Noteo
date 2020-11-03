<?php

namespace App\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class EnseignantVoter extends Voter
{
    protected function supports($attribute, $subject)
    {
        return in_array($attribute, ['ENSEIGNANT_EDIT', 'ENSEIGNANT_NEW', 'ENSEIGNANT_DELETE', 'ENSEIGNANT_INDEX', 'ENSEIGNANT_SHOW'])
            && $subject instanceof \App\Entity\Enseignant;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return false;
        }
        $accesAutorise = false;
        switch ($attribute) {
            case 'ENSEIGNANT_INDEX':
            case 'ENSEIGNANT_NEW':
                $accesAutorise = in_array("ROLE_ADMIN", $user->getRoles());
                break;
            case 'ENSEIGNANT_EDIT':
            case 'ENSEIGNANT_SHOW' :
                //Cette fonctionnalité est dispo si l'utilisateur est admin ou que l'enseignant consulte son propre profil
                $accesAutorise = in_array("ROLE_ADMIN", $user->getRoles()) || $subject->getId() == $user->getId();
                break;
            case 'ENSEIGNANT_DELETE' :
                //Cette fonctionnalité n'est pas disponible si ce n'est pas un admin ou si l'admin supprime son propre profil
                $accesAutorise = in_array("ROLE_ADMIN", $user->getRoles()) && $subject->getId() != $user->getId();
                break;
        }
        return $accesAutorise;
    }
}
