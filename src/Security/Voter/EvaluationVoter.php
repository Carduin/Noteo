<?php

namespace App\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class EvaluationVoter extends Voter
{
    protected function supports($attribute, $subject)
    {
        return in_array($attribute, ['EVALUATION_PREVISUALISATION_MAIL', 'EVALUATION_EXEMPLE_MAIL', 'EVALUATION_ENVOI_MAIL', 'EVALUATION_EDIT', 'EVALUATION_DELETE'])
            && $subject instanceof \App\Entity\Evaluation;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return false;
        }
        $accesAutorise = true;
        switch ($attribute) {
            case 'EVALUATION_PREVISUALISATION_MAIL':
            case 'EVALUATION_EXEMPLE_MAIL':
            case 'EVALUATION_ENVOI_MAIL':
            case 'EVALUATION_EDIT':
            case 'EVALUATION_DELETE':
                //Accès autorisé si on est admin ou propriétaire de l'évaluation
                $accesAutorise = in_array("ROLE_ADMIN", $user->getRoles()) || $subject->getEnseignant()->getId() == $user->getId();
                break;
        }
        return $accesAutorise;
    }
}
