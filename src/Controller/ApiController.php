<?php

namespace App\Controller;

use App\Entity\Partie;
use App\Manager\StatisticsManager;
use App\Repository\EvaluationRepository;
use App\Repository\GroupeEtudiantRepository;
use App\Repository\PartieRepository;
use App\Repository\StatutRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/*
 * Format erreurs :
 * [
 *   'type' : 'Bad parameter'
 *   'target' : 'Classe entité'
 * ]
 *
 * Codes fonctionnement :
 *
 * 1 : Success
 * 2 : Success with errors
 * 3 : Aborted
 */

/**
 * @Route("/api")
 */
class ApiController extends AbstractController
{
    private $statisticsManager;
    private $serializer;
    private $groupeEtudiantRepository;
    private $evaluationRepository;
    private $statutRepository;
    private $partiesRepository;
    private $squeletteTableauRetour;
    private $tableauRetourCourant;

    public function __construct(SerializerInterface $serializer, StatisticsManager $statisticsManager, GroupeEtudiantRepository $groupeEtudiantRepository, EvaluationRepository $evaluationRepository, StatutRepository $statutRepository, PartieRepository $partieRepository) {
        $this->serializer = $serializer;
        $this->groupeEtudiantRepository = $groupeEtudiantRepository;
        $this->evaluationRepository = $evaluationRepository;
        $this->statutRepository = $statutRepository;
        $this->partiesRepository = $partieRepository;
        $this->statisticsManager = $statisticsManager;
        $this->squeletteTableauRetour = [
            'code' => 1,
            'errors' => [],
            'type' => '',
            'statisticsData' => []
        ];
        $this->tableauRetourCourant = $this->squeletteTableauRetour;
    }

    /**
     * @Route("/statistiques/evaluationSimple", name="api_get_stats_eval_simple", methods={"GET", "POST"})
     */
    public function getStatistiquesSimples(Request $request)
    {
        // Reinitialisation des dernières données calculées
        $this->tableauRetourCourant = $this->squeletteTableauRetour;
        $this->tableauRetourCourant['type'] = 'evaluationSimple';
        // Récupération des paramètres
        $objetEvaluation = $this->fetchUneEvaluation($request->get('evaluation'));
        if(!$objetEvaluation) {
            $this->tableauRetourCourant['code'] = 3; // Si pas d'éval : impossible de continuer
        }
        $objetsParties = $this->fetchParties($request->get('parties'), $objetEvaluation);
        if(empty($objetsParties)) {
            $this->tableauRetourCourant['code'] = 3; // Si pas de parties : impossible de continuer
        }
        $objetsGroupes = $this->fetchGroupes($request->get('groupes'));
        $objetsStatuts = $this->fetchStatuts($request->get('statuts'));
        if(empty($objetsGroupes) && empty($objetsStatuts)) {
            $this->tableauRetourCourant['code'] = 3; // Impossible de continuer sans groupes ni statut
        }
        if ($this->tableauRetourCourant['code'] != 3 ) {
            $this->tableauRetourCourant['statisticsData'] = $this->statisticsManager->calculerStatsClassiques($objetEvaluation, $objetsGroupes, $objetsStatuts, $objetsParties);
        }
        return new Response($this->serializer->serialize($this->tableauRetourCourant, 'json'));
    }

    public function fetchStatuts($statutsGETParameter) {
        $objetsStatuts = [];
        if($statutsGETParameter) {
            foreach ($statutsGETParameter as $statut) {
                $objetsStatut = $this->statutRepository->findOneById($statut);
                if ($objetsStatut) {
                    $objetsStatuts[] = $objetsStatut;
                }
                else {
                    $this->tableauRetourCourant['errors'][] = [
                        'type' => 'Bad Parameter' ,
                        'target' => 'Statut'
                    ];
                    if ($this->tableauRetourCourant['code'] != 3) { // Ne pas override le code 3
                        $this->tableauRetourCourant['code'] = 2; // Erreur survenue
                    }
                }

            }
        }
        return $objetsStatuts;
    }

    public function fetchGroupes($groupesGETParameter) {
        $objetsGroupes = [];
        if($groupesGETParameter) {
            foreach ($groupesGETParameter as $groupe) {
                $objetsGroupe = $this->groupeEtudiantRepository->findOneById($groupe);
                if ($objetsGroupe) {
                    $objetsGroupes[] = $objetsGroupe;
                }
                else {
                    $this->tableauRetourCourant['errors'][] = [
                        'type' => 'Bad Parameter' ,
                        'target' => 'Groupe'
                    ];
                    if ($this->tableauRetourCourant['code'] != 3) { // Ne pas override le code 3
                        $this->tableauRetourCourant['code'] = 2; // Erreur survenue
                    }
                }
            }
        }
        return $objetsGroupes;
    }

    public function fetchUneEvaluation($evaluationGETParameter) {
        $objetEvaluation = $this->evaluationRepository->findOneById($evaluationGETParameter);
        if(!$objetEvaluation) {
            $this->tableauRetourCourant['errors'][] = [
                'type' => 'Bad Parameter' ,
                'target' => 'Evaluation'
            ];
            if ($this->tableauRetourCourant['code'] != 3) { // Ne pas override le code 3
                $this->tableauRetourCourant['code'] = 2; // Erreur survenue
            }
        }
        return $objetEvaluation;
    }


    public function fetchPlusieursEvaluations($evaluationsGETParameter) {

    }

    public function fetchParties($partiesGETParameter, $objetEvaluation) {
        $objetsParties = [];
        if ($partiesGETParameter) {
            foreach ($partiesGETParameter as $partie) {
                $objetPartie = $this->partiesRepository->findOneById($partie);
                if($objetPartie && $objetPartie->getEvaluation()->getId() == $objetEvaluation->getId()) {
                    $objetsParties[] = $objetPartie;
                }
                else { // Si la partie ne correspond pas à l'évaluation choisie
                    $this->tableauRetourCourant['errors'][] = [
                        'type' => 'Bad Parameter' ,
                        'target' => 'Partie'
                    ];
                    if ($this->tableauRetourCourant['code'] != 3) { // Ne pas override le code 3
                        $this->tableauRetourCourant['code'] = 2; // Erreur survenue
                    }
                }
            }
        }
        else {
            $objetsParties[] = $objetEvaluation->getParties()[0];
        }
        return $objetsParties;
    }
}
