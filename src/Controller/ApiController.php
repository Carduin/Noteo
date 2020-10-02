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
 *   'type' : 'Bad parameter' || 'Not Allowed to use'
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
    }

    /**
     * @Route("/statistiques/evaluationSimple", name="api_get_stats_eval_simple", methods={"GET", "POST"})
     */
    public function getStatistiquesSimples(Request $request)
    {
        //Preparation du tableau renvoyé
        $returnArray = $this->squeletteTableauRetour;
        $returnArray['type'] = 'evaluationSimple';

        //Récupération et vérification des paramètres
        //evaluation
        $evaluation = $request->get('evaluation');
        $objetEvaluation = $this->evaluationRepository->findOneById($evaluation);
        if(!$objetEvaluation) {
            $returnArray['errors'][] = [
                'type' => 'Bad Parameter' ,
                'target' => 'Evaluation'
            ];
            $returnArray['code'] = 3; //Impossible de continuer sans évaluation
        }
        //parties
        $parties = $request->get('parties');
        $objetsParties = [];
        if ($parties) {
            foreach ($parties as $partie) {
                $objetPartie = $this->partiesRepository->findOneById($partie);
                if($objetsParties && $objetPartie->getEvaluation()->getId() == $objetEvaluation->getId()) {
                    $objetsParties[] = $objetsParties;
                }
                else {
                    $returnArray['errors'][] = [
                        'type' => 'Bad Parameter' ,
                        'target' => 'Partie'
                    ];
                    $returnArray['code'] = 2; // Erreur survenue
                }

            }
        }
        else {
            $objetsParties[] = $objetEvaluation->getParties()[0];
        }
        //groupes
        $groupes = $request->get('groupes');
        $objetsGroupes = [];
        if($groupes) {
            foreach ($groupes as $groupe) {
                $objetsGroupe = $this->groupeEtudiantRepository->findOneById($groupe);
                if ($objetsGroupe) {
                    $objetsGroupes[] = $objetsGroupe;
                }
                else {
                    $returnArray['errors'][] = [
                        'type' => 'Bad Parameter' ,
                        'target' => 'Groupe'
                    ];
                    $returnArray['code'] = 2; // Erreur survenue
                }
            }
        }

        //statuts
        $statuts = $request->get('statuts');
        $objetsStatuts = [];
        if($statuts) {
            foreach ($statuts as $statut) {
                $objetsStatut = $this->statutRepository->findOneById($statut);
                if ($objetsStatut) {
                    $objetsStatuts[] = $objetsStatut;
                }
                else {
                    $returnArray['errors'][] = [
                        'type' => 'Bad Parameter' ,
                        'target' => 'Statut'
                    ];
                    $returnArray['code'] = 2; // Erreur survenue
                }

            }
        }

        if(empty($objetsGroupes) && empty($objetsStatuts)) {
            $returnArray['code'] = 3; // Impossible de continuer sans groupes ni statut
        }

        if ($returnArray['code'] != 3 ) {
            $returnArray['statisticsData'] = $this->statisticsManager->calculerStatsClassiques($objetEvaluation, $objetsGroupes, $objetsStatuts, $objetsParties);
        }
        //Renvoi des statistiques
        return new Response($this->serializer->serialize($returnArray, 'json'));
    }
}
