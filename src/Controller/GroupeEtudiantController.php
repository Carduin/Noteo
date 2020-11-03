<?php

namespace App\Controller;

use App\Entity\Etudiant;
use App\Entity\GroupeEtudiant;
use App\Form\GroupeEtudiantType;
use App\Form\SousGroupeEtudiantType;
use App\Form\GroupeEtudiantEditType;
use App\Repository\EtudiantRepository;
use App\Repository\GroupeEtudiantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @Route("{_locale}/groupe")
 */
class GroupeEtudiantController extends AbstractController
{
    const COLONNE_NOM = 0;
    const COLONNE_PRENOM = 1;
    const COLONNE_MAIL = 2;

    /**
     * @Route("/", name="groupe_etudiant_index", methods={"GET"})
     */
    public function index(GroupeEtudiantRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('GROUPE_INDEX', new GroupeEtudiant());
        return $this->render('groupe_etudiant/index.html.twig', [
            'groupes' => $repo->findAllOrderedAndWithoutSpace()
        ]);
    }

    /**
     * @Route("/nouveau", name="groupe_etudiant_new", methods={"GET","POST"})
     */
    public function new(Request $request, GroupeEtudiantRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('GROUPE_NEW', new GroupeEtudiant());
        $nbGroupesDansAppli = count($repo->findAll());
        //Si le nombre de groupes est supérieur à 1 il y a un groupe de haut niveau créé : on ne peut alors plus en créer
        if ($nbGroupesDansAppli > 1) {
            throw new AccessDeniedException('Accès refusé');
        }
        $groupeEtudiant = new GroupeEtudiant();
        $groupeEtudiant->setEnseignant($this->getUser());
        $form = $this->createForm(GroupeEtudiantType::class, $groupeEtudiant);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            if ($repo->findOneBySlug('etudiants-non-affectes') == null) {
                $nonAffectes = new GroupeEtudiant();
                $nonAffectes->setNom("Etudiants non affectés");
                $nonAffectes->setDescription("Tous les étudiants ayant été retirés d'un groupe de haut niveau et ne faisant partie d'aucun groupe");
                $nonAffectes->setEnseignant($this->getUser());
                $nonAffectes->setEstEvaluable(false);
                $entityManager->persist($nonAffectes);
            }
            $groupeEtudiant->setEnseignant($this->getUser());
            $entityManager->persist($groupeEtudiant);
            $fichierCSV = $form['fichier']->getData();
            $fichier = fopen($fichierCSV, "r");
            $nbLignes = count(file($fichierCSV));
            $premiereLigne = chop(fgets($fichier));
            if ($premiereLigne === "NOM;PRENOM;MAIL") {
                for ($i = 0; $i < $nbLignes - 1; $i++) {
                    $ligneCourante = fgets($fichier);
                    $etudiantCourant = explode(";", $ligneCourante);
                    $etudiant = new Etudiant();
                    $etudiant->setNom($etudiantCourant[GroupeEtudiantController::COLONNE_NOM]);
                    $etudiant->setPrenom($etudiantCourant[GroupeEtudiantController::COLONNE_PRENOM]);
                    $etudiant->setMail($etudiantCourant[GroupeEtudiantController::COLONNE_MAIL]);
                    $etudiant->setEstDemissionaire(false);
                    $etudiant->addGroupe($groupeEtudiant);
                    $entityManager->persist($etudiant);
                }
            }
            $entityManager->flush();
            return $this->redirectToRoute('groupe_etudiant_index');
        }
        return $this->render('groupe_etudiant/new.html.twig', [
            'groupe_etudiant' => $groupeEtudiant,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/consulter/{slug}", name="groupe_etudiant_show", methods={"GET"})
     */
    public function show(GroupeEtudiant $groupeEtudiant): Response
    {
        $this->denyAccessUnlessGranted('GROUPE_SHOW', $groupeEtudiant);
        return $this->render('groupe_etudiant/show.html.twig', [
            'groupe_etudiant' => $groupeEtudiant,
            'etudiants' => $groupeEtudiant->getEtudiants()
        ]);
    }

    /**
     * @Route("/modifier/{slug}", name="groupe_etudiant_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, GroupeEtudiant $groupeEtudiant, EtudiantRepository $repoEtud): Response
    {
        $this->denyAccessUnlessGranted('GROUPE_EDIT', $groupeEtudiant);
        $enfantsGroupeModifie = $this->getDoctrine()->getRepository(GroupeEtudiant::class)->children($groupeEtudiant);
        $GroupeDesNonAffectes = $this->getDoctrine()->getRepository(GroupeEtudiant::class)->findOneBySlug("etudiants-non-affectes");
        if ($groupeEtudiant->getParent() == null) {
            $groupeAPartirDuquelAjouterEtudiants = $GroupeDesNonAffectes;
        } else {
            $groupeAPartirDuquelAjouterEtudiants = $groupeEtudiant->getParent();
        }
        //Tout les étudiants dans le groupe supérieur, qui ne sont pas dans le groupe courant
        $listeEtudiantsPourAjout = $repoEtud->findAllFromGroupParentButNotCurrent($groupeAPartirDuquelAjouterEtudiants, $groupeEtudiant);
        $form = $this->createForm(GroupeEtudiantEditType::class, $groupeEtudiant, ['GroupeAjout' => $listeEtudiantsPourAjout, 'estEvaluable' => $groupeEtudiant->getEstEvaluable()]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($groupeEtudiant->getParent() == null) { //Groupe le plus haut de la hierarchie
                foreach ($form->get('etudiantsAAjouter')->getData() as $key => $etudiant) {
                    $groupeEtudiant->addEtudiant($etudiant);
                    $GroupeDesNonAffectes->removeEtudiant($etudiant);
                }
                foreach ($form->get('etudiantsASupprimer')->getData() as $key => $etudiant) {
                    foreach ($enfantsGroupeModifie as $enfant) {
                        $enfant->removeEtudiant($etudiant);
                    }
                    $groupeEtudiant->removeEtudiant($etudiant);
                    $GroupeDesNonAffectes->addEtudiant($etudiant);
                }
            } else { //Groupe "classique"
                foreach ($form->get('etudiantsAAjouter')->getData() as $key => $etudiant) {
                    $groupeEtudiant->addEtudiant($etudiant);
                }
                foreach ($form->get('etudiantsASupprimer')->getData() as $key => $etudiant) {
                    //On supprime l'étudiant des sous groupes
                    foreach ($enfantsGroupeModifie as $enfant) {
                        $enfant->removeEtudiant($etudiant);
                    }
                    $groupeEtudiant->removeEtudiant($etudiant);
                }
            }
            $this->getDoctrine()->getManager()->persist($groupeEtudiant);
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('groupe_etudiant_show', [
                'slug' => $groupeEtudiant->getSlug()
            ]);
        }
        return $this->render('groupe_etudiant/edit.html.twig', [
            'form' => $form->createView(),
            'groupe_etudiant' => $groupeEtudiant,
            'edit' => true
        ]);
    }

    /**
     * @Route("/supprimer/{slug}", name="groupe_etudiant_delete", methods={"GET"})
     */
    public function delete(Request $request, GroupeEtudiant $groupeEtudiant): Response
    {
        $this->denyAccessUnlessGranted('GROUPE_DELETE', $groupeEtudiant);
        $em = $this->getDoctrine()->getManager();
        $repo = $this->getDoctrine()->getRepository(GroupeEtudiant::class);
        $groupesASupprimer = $repo->children($groupeEtudiant);
        $groupesASupprimer[] = $groupeEtudiant;
        foreach ($groupesASupprimer as $groupe) {
            foreach ($groupe->getEvaluations() as $evaluation) {
                foreach ($evaluation->getParties() as $partie) {
                    foreach ($partie->getNotes() as $note) {
                        $em->remove($note);
                    }
                    $em->remove($partie);
                }
                $em->remove($evaluation);
            }
        }
        //Si groupe le plus haut de la hierarchie
        if ($groupeEtudiant->getParent() == null) {
            foreach ($groupeEtudiant->getEtudiants() as $etudiant) {
                $em->remove($etudiant);
            }
        }
        $em->remove($groupeEtudiant); // On supprime le groupeEtudiant, ce qui grâce à Tree a pour effet de supprimer les enfants en cascade
        $em->flush();
        return $this->redirectToRoute('groupe_etudiant_index');
    }

    /**
     * @Route("/nouveau/sous-groupe/{slug}", name="groupe_etudiant_new_sousGroupe", methods={"GET","POST"})
     */
    public function newSousGroupe(GroupeEtudiant $groupeEtudiantParent, Request $request): Response
    {
        $this->denyAccessUnlessGranted('GROUPE_NEW_SOUS_GROUPE', $groupeEtudiantParent);
        $groupeEtudiant = new GroupeEtudiant();
        $groupeEtudiant->setParent($groupeEtudiantParent);
        $form = $this->createForm(SousGroupeEtudiantType::class, $groupeEtudiant, ['parent' => $groupeEtudiantParent]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $groupeEtudiant->setEnseignant($this->getUser());
            foreach ($form->get('etudiantsAAjouter')->getData() as $key => $etudiant) {
                $groupeEtudiant->addEtudiant($etudiant);
            }
            $this->getDoctrine()->getManager()->persist($groupeEtudiant);
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('groupe_etudiant_index');
        }
        return $this->render('groupe_etudiant/newSousGroupe.html.twig', [
            'form' => $form->createView(),
            'nomParent' => $groupeEtudiantParent->getNom(),
            'edit' => false
        ]);
    }
}
