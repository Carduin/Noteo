<?php

namespace App\Controller;

use App\Entity\Enseignant;
use App\Manager\StatisticsManager;
use App\Repository\EtudiantRepository;
use App\Repository\EvaluationRepository;
use App\Repository\GroupeEtudiantRepository;
use App\Repository\PartieRepository;
use App\Repository\StatutRepository;
use App\Entity\ApiLog;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/*
 * Format erreurs :
 * [
 *   'type' : 'Bad parameter'
 *   'target' : 'Entity name'
 * ]
 *
 * Codes de fonctionnement de l'API :
 *
 * 1 : Success
 * 2 : Success with non-critical errors
 * 3 : Aborted - Critical errors
 */

/**
 * @Route("/api")
 */
class ApiController extends AbstractController
{
    private $statisticsManager;
    private $serializer;
    private $groupeEtudiantRepository;
    private $etudiantRepository;
    private $evaluationRepository;
    private $statutRepository;
    private $partiesRepository;
    private $squeletteTableauRetour;
    private $tableauRetourCourant;

    public function __construct(SerializerInterface $serializer, StatisticsManager $statisticsManager, GroupeEtudiantRepository $groupeEtudiantRepository, EvaluationRepository $evaluationRepository, StatutRepository $statutRepository, PartieRepository $partieRepository, EtudiantRepository $etudiantRepository) {
        $this->serializer = $serializer;
        $this->groupeEtudiantRepository = $groupeEtudiantRepository;
        $this->evaluationRepository = $evaluationRepository;
        $this->statutRepository = $statutRepository;
        $this->etudiantRepository = $etudiantRepository;
        $this->partiesRepository = $partieRepository;
        $this->statisticsManager = $statisticsManager;
        $this->squeletteTableauRetour = [
            'code' => 1,
            'errors' => [],
            'type' => '',
            'evaluations' => [],
            'statisticsData' => []
        ];
        $this->tableauRetourCourant = $this->squeletteTableauRetour;
    }

    /**
     * @Route("/statistiques", name="api_get_stats", methods={"GET", "POST"})
     */
    public function getStatistiques(Request $request) {
        $this->tableauRetourCourant = $this->squeletteTableauRetour;
        $typeStatistiques = $request->get('type');
        if($typeStatistiques) {
            $this->tableauRetourCourant['type'] = $typeStatistiques;
            switch ($typeStatistiques) {
                case 'classique' :
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
                    break;
                case 'plusieurs-evaluations-groupes':
                    $objetsGroupes = $this->fetchGroupes($request->get('groupes'));
                    $objetsEvaluations = $this->fetchPlusieursEvaluations($request->get('evaluations'));
                    if (empty($objetsGroupes) || empty($objetsEvaluations)) {
                        $this->tableauRetourCourant['code'] = 3; // Impossible de continuer sans groupes ou evaluations
                        $this->tableauRetourCourant['errors'][] = [
                            'type' => 'Missing critical parameter' ,
                            'target' => 'Evaluation or Groupe'
                        ];
                    }
                    if ($this->tableauRetourCourant['code'] != 3 ) {
                        $this->tableauRetourCourant['statisticsData'] = $this->statisticsManager->calculerStatsPlusieursEvals('groupes', $objetsGroupes, $objetsEvaluations);
                    }
                    break;
                case 'plusieurs-evaluations-statut':
                    $objetsStatut = $this->fetchStatuts($request->get('statuts'));
                    $objetsEvaluations = $this->fetchPlusieursEvaluations($request->get('evaluations'));
                    if (empty($objetsGroupes) || empty($objetsEvaluations)) {
                        $this->tableauRetourCourant['code'] = 3; // Impossible de continuer sans evaluation ou statuts
                        $this->tableauRetourCourant['errors'][] = [
                            'type' => 'Missing critical parameter' ,
                            'target' => 'Evaluation or Statut'
                        ];
                    }
                    if ($this->tableauRetourCourant['code'] != 3 ) {
                        $this->tableauRetourCourant['statisticsData'] = $this->statisticsManager->calculerStatsPlusieursEvals('statuts', $objetsStatut, $objetsEvaluations);
                    }
                    break;
                case 'evolution-groupe' :
                    $objetsGroupes = $this->fetchGroupes($request->get('groupes'));
                    $objetsEvaluations = $this->fetchPlusieursEvaluations($request->get('evaluations'));
                    if(!$objetsEvaluations || !$objetsGroupes) {
                        $this->tableauRetourCourant['code'] = 3; // Impossible de continuer sans groupes ou evaluations
                        $this->tableauRetourCourant['errors'][] = [
                            'type' => 'Missing critical parameter' ,
                            'target' => 'Groupes or Evaluations'
                        ];
                    }
                    if ($this->tableauRetourCourant['code'] != 3 ) {
                        $this->tableauRetourCourant['statisticsData'] = $this->statisticsManager->calculerStatsEvolution('groupe', $objetsGroupes, $objetsEvaluations);
                    }
                    break;
                case 'evolution-statut' :
                    $objetsStatuts = $this->fetchStatuts($request->get('statuts'));
                    $objetsGroupes = $this->fetchGroupes($request->get('groupes'));
                    $objetsEvaluations = $this->fetchPlusieursEvaluations($request->get('evaluations'));
                    if(!$objetsEvaluations || !$objetsGroupes || !$objetsStatuts) {
                        $this->tableauRetourCourant['code'] = 3; // Impossible de continuer sans groupes ou evaluations
                        $this->tableauRetourCourant['errors'][] = [
                            'type' => 'Missing critical parameter' ,
                            'target' => 'Groupes or Statut or Evaluations'
                        ];
                    }
                    if ($this->tableauRetourCourant['code'] != 3 ) {
                        $this->tableauRetourCourant['statisticsData'] = $this->statisticsManager->calculerStatsEvolution('statut', $objetsGroupes, $objetsEvaluations, $objetsStatuts[0]);
                    }
                    break;
                case 'comparaison':
                    $objetEvaluationReference = $this->fetchUneEvaluation($request->get('evaluationReference'));
                    $objetsAutresEvaluations = $this->fetchPlusieursEvaluations($request->get('autresEvaluations'));
                    $objetsGroupes = $this->fetchGroupes($request->get('groupes'));
                    $objetsStatuts = $this->fetchStatuts($request->get('statuts'));
                    if (!$objetEvaluationReference || !$objetsAutresEvaluations || (!$objetsGroupes && !$objetsStatuts)) { //Si pas d'évaluation de référence, pas d'autres évaluations à comparer ou pas de groupes et de statut choisi
                        $this->tableauRetourCourant['code'] = 3;
                        $this->tableauRetourCourant['errors'][] = [
                            'type' => 'Missing critical parameters' ,
                            'target' => 'Unknown'
                        ];
                    }
                    if ($this->tableauRetourCourant['code'] != 3 ) {
                        $this->tableauRetourCourant['statisticsData'] = $this->statisticsManager->calculerStatsComparaison($objetEvaluationReference, $objetsGroupes, $objetsStatuts, $objetsAutresEvaluations);
                    }
                    break;
                case 'fiche-etudiant':
                    $objetEtudiant = $this->fetchUnEtudiant($request->get('etudiant'));
                    if(!$objetEtudiant) {
                        $this->tableauRetourCourant['code'] = 3;
                        $this->tableauRetourCourant['errors'][] = [
                            'type' => 'Missing or bad critical parameter' ,
                            'target' => 'Etudiant'
                        ];
                    }
                    if ($this->tableauRetourCourant['code'] != 3 ) {
                        $groupes = array();
                        foreach ($objetEtudiant->getGroupes() as $groupe) {
                            if ($groupe->getEstEvaluable() == true) {
                                array_push($groupes, $groupe);
                            }
                        }
                        $this->tableauRetourCourant['statisticsData'] = $this->statisticsManager->calculerStatsFicheEtudiant($objetEtudiant, $this->evaluationRepository->findAllByEtudiant($objetEtudiant->getId()), $groupes, $objetEtudiant->getStatuts());
                    }
                    break;
                default:
                    $this->tableauRetourCourant['code'] = 3;
                    $this->tableauRetourCourant['errors'][] = [
                        'type' => 'Bad critical parameter' ,
                        'target' => 'Statistics type'
                    ];
                    $typeStatistiques = 'Non recognized';
                    break;
            }
        }
        else {
            $this->tableauRetourCourant['code'] = 3;
            $this->tableauRetourCourant['errors'][] = [
                'type' => 'Missing critical parameter' ,
                'target' => 'Statistics type'
            ];
            $typeStatistiques = 'Missing';
        }
        $em = $this->getDoctrine()->getManager();
        $log = new ApiLog();
        $log->setEnseignant($em->getRepository(Enseignant::class)->findOneByToken($request->get('token')));
        $log->setType($typeStatistiques);
        $log->setCalledAt(new \DateTime());
        $em->persist($log);
        $em->flush();
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
                        $this->tableauRetourCourant['code'] = 2;
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
                        $this->tableauRetourCourant['code'] = 2;
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
                $this->tableauRetourCourant['code'] = 2;
            }
        }
        else {
            $this->tableauRetourCourant['evaluations'][] = $objetEvaluation->getNom();
        }
        return $objetEvaluation;
    }

    public function fetchUnEtudiant($etudiantGETParameter) {
        $objetEtudiant = $this->etudiantRepository->findOneById($etudiantGETParameter);
        if(!$objetEtudiant) {
            $this->tableauRetourCourant['errors'][] = [
                'type' => 'Bad Parameter' ,
                'target' => 'Etudiant'
            ];
            if ($this->tableauRetourCourant['code'] != 3) { // Ne pas override le code 3
                $this->tableauRetourCourant['code'] = 2;
            }
        }
        return $objetEtudiant;
    }


    public function fetchPlusieursEvaluations($evaluationsGETParameter) {
        $objetsEvaluations = [];
        if ($evaluationsGETParameter) {
            foreach ($evaluationsGETParameter as $evaluation) {
                $objetEvaluation = $this->evaluationRepository->findOneById($evaluation);
                if ($objetEvaluation) {
                    $objetsEvaluations[] = $objetEvaluation;
                    $this->tableauRetourCourant['evaluations'][] = $objetEvaluation->getNom();
                }
                else {
                    $this->tableauRetourCourant['errors'][] = [
                        'type' => 'Bad Parameter' ,
                        'target' => 'Evaluation'
                    ];
                    if ($this->tableauRetourCourant['code'] != 3) { // Ne pas override le code 3
                        $this->tableauRetourCourant['code'] = 2;
                    }
                }
            }
        }
        return $objetsEvaluations;
    }

    public function fetchParties($partiesGETParameter, $objetEvaluation) {
        $objetsParties = [];
        if ($objetEvaluation) {
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
                            $this->tableauRetourCourant['code'] = 2;
                        }
                    }
                }
            }
            else {
                $objetsParties[] = $objetEvaluation->getParties()[0];
            }
        }
        return $objetsParties;
    }
}
