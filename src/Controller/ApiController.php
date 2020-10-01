<?php

namespace App\Controller;

use App\Repository\GroupeEtudiantRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @Route("/api")
 */
class ApiController extends AbstractController
{
    private $serializer;
    private $groupeEtudiantRepository;
    private $squeletteTableauRetour;

    public function __construct(SerializerInterface $serializer, GroupeEtudiantRepository $groupeEtudiantRepository) {
        $this->serializer = $serializer;
        $this->groupeEtudiantRepository = $groupeEtudiantRepository;
        $this->squeletteTableauRetour = [
            'code' => 0,
            'errors' => [],
            'data' => [
                'type' => '',
                'statisticsData' => []
            ]
        ];
    }

    /**
     * @Route("/statistiques/evaluationSimple", name="api_get_stats_eval_simple", methods={"GET"})
     */
    public function getStatistiquesSimples(Request $request)
    {
        //Preparation tableau renvoyé
        $returnArray = $this->squeletteTableauRetour;
        $returnArray['data']['type'] = 'evaluationSimple';

        //Récupération et vérification des paramètres

        //Calcul

        //Renvoi des statistiques

        return new Response($this->serializer->serialize($returnArray, 'json'));
    }
}
