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
            'data' => [
                'type' => '',
                'statisticsData' => []
            ]
        ];
    }

    /**
     * @Route("/statistiques/evaluationSimple", name="api_get_stats_eval_simple", methods={"GET", "POST"})
     */
    public function getStatistiquesSimples(Request $request)
    {
        //Preparation du tableau renvoyé
        $returnArray = $this->squeletteTableauRetour;
        $returnArray['data']['type'] = 'evaluationSimple';

        //Récupération et vérification des paramètres
        //evaluation
        $evaluation = $request->get('evaluation');
        $evaluationObject = $this->evaluationRepository->findOneByNom($evaluation);
        if(!$evaluationObject) {
            $returnArray['errors'][] = [
                'type' => 'Bad Parameter' ,
                'target' => 'Evaluation'
            ];
            $returnArray['code'] = 3; //Impossible de continuer sans évaluation
        }
        //parties
        $parties = $request->get('parties');
        if ($parties) {
            foreach ($evaluationObject->getParties() as $partieEvaluation) {
                for ($i=0 ; $i < count($parties)-1 ; $i++) {
                    if (strcmp($partieEvaluation->getIntitule(), $parties[$i]) == 0) {
                        $objetsParties[] = $partieEvaluation;
                        unset($parties[$i]);
                    }
                }
            }
            if (!empty($parties)) {
                var_dump($parties);
            }
        }
        else {
            $objetsParties[] = $evaluationObject->getParties();
        }

        if ($returnArray['code'] != 3 ) {
            //$returnArray['data']['statisticsData'] = $this->statisticsManager->calculerStatsClassiques($evaluationObject, $groupes, $statuts, $objetsParties);
        }
        //Renvoi des statistiques
        return new Response($this->serializer->serialize($returnArray, 'json'));
    }
}
