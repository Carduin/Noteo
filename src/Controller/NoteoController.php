<?php

namespace App\Controller;

use App\Repository\ApiLogRepository;
use App\Repository\EtudiantRepository;
use App\Repository\GroupeEtudiantRepository;
use App\Repository\StatutRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class NoteoController extends AbstractController
{
    /**
     * @Route("/", name="noteo_login")
     */
    public function index()
    {
        return $this->redirectToRoute('app_login');
    }

    /**
     * @Route("/{_locale}/tutoriels", name="tutoriels")
     */
    public function tutoriels()
    {
        return $this->render('tutoriels/page_tutoriels.html.twig');
    }

    /**
     * @Route("/{_locale}/historique-api", name="historique_api")
     */
    public function historiqueApi(ApiLogRepository $repoAPI)
    {
        $this->denyAccessUnlessGranted("API_HISTORY", $this->getUser());
        return $this->render('API/historique_api.html.twig', [
            'logs' => $repoAPI->getAllLogsWithEnseignants()
        ]);
    }

    /**
     * @Route("/{_locale}/historique-api/supprimer", name="empty_historique_api")
     */
    public function viderHistoriqueApi()
    {
        $this->denyAccessUnlessGranted("API_HISTORY", $this->getUser());
        $connection = $this->getDoctrine()->getManager()->getConnection();
        $platform = $connection->getDatabasePlatform();
        $connection->executeQuery('SET FOREIGN_KEY_CHECKS = 0;'); // Pour éviter les erreurs de clé étrangeres lors du TRUNCATE
        $connection->executeUpdate($platform->getTruncateTableSQL('api_log'));
        return $this->redirectToRoute('historique_api');
    }

    /**
     * @Route("/reinitialiser", name="app_reset")
     */
    public function reinitialiserApplication(GroupeEtudiantRepository $repoGroupe, StatutRepository $repoStatut, EtudiantRepository $repoEtudiant)
    {
        $this->denyAccessUnlessGranted("RESET_APPLICATION", $this->getUser());
        $entityManager = $this->getDoctrine()->getManager();
        foreach ($repoGroupe->findAll() as $groupe) {
            foreach ($groupe->getEvaluations() as $evaluation) {
                foreach ($evaluation->getParties() as $partie) {
                    foreach ($partie->getNotes() as $note) {
                        $entityManager->remove($note);
                    }
                    $entityManager->remove($partie);
                }
                $entityManager->remove($evaluation);
            }
            $entityManager->remove($groupe);
        }
        foreach ($repoStatut->findAll() as $statut) {
            $entityManager->remove($statut);
        }
        foreach ($repoEtudiant->findAll() as $etudiant) {
            $entityManager->remove($etudiant);
        }
        $entityManager->flush();
        return $this->redirectToRoute("groupe_etudiant_index");
    }
}
