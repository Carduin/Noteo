<?php

namespace App\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class GroupeVoter extends Voter
{
    protected function supports($attribute, $subject)
    {
        return in_array($attribute, ['GROUPE_EDIT', 'GROUPE_DELETE', 'GROUPE_SHOW', 'GROUPE_INDEX', 'GROUPE_NEW', 'GROUPE_NEW_SOUS_GROUPE'])
            && $subject instanceof \App\Entity\GroupeEtudiant;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return false;
        }
        $accesAutorise = false;
        switch ($attribute) {
            case 'GROUPE_NEW' :
            case 'GROUPE_NEW_SOUS_GROUPE':
            case 'GROUPE_EDIT':
            case 'GROUPE_DELETE':
                //Il faut être admin pour pouvoir modifier/supprimer un groupe, et un créer un nouveau
                $accesAutorise = in_array("ROLE_ADMIN", $user->getRoles());
                break;
            case 'GROUPE_SHOW':
            case 'GROUPE_INDEX':
                $accesAutorise = true;
                break;
        }
        return $accesAutorise;
    }
}
