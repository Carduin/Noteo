<?php

namespace App\Controller;

use App\Entity\Etudiant;
use App\Entity\GroupeEtudiant;
use App\Form\GroupeEtudiantType;
use App\Form\SousGroupeEtudiantType;
use App\Form\GroupeEtudiantEditType;
use App\Repository\EtudiantRepository;
use App\Repository\GroupeEtudiantRepository;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
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
        //On compte le nombre de groupes présents dans l'application
        $nbGroupesDansAppli = count($repo->findAll());
        //Si le nombre de groupes est supérieur à 1 il y a un groupe de haut niveau créé : on ne peut alors plus en créer
        // on jette une erreur car l'utilisateur n'est pas censé avoir accès à cette fonctionnalité dans ce cas la
        if ($nbGroupesDansAppli > 1) {
            throw new AccessDeniedException('Accès refusé');
        }
        $groupeEtudiant = new GroupeEtudiant();
        $groupeEtudiant->setEnseignant($this->getUser());
        $form = $this->createForm(GroupeEtudiantType::class, $groupeEtudiant);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            //Si le groupe des étudiants non affectés n'existe pas déjà on le crée
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
            // Récupération du fichier CSV
            $fichierCSV = $form['fichier']->getData();
            // Ouverture du fichier CSV
            $fichier = fopen($fichierCSV, "r");
            // Pour éviter la dernière ligne du fichier
            $nbLignes = count(file($fichierCSV));
            // Récupération première ligne du fichier
            $ligne = chop(fgets($fichier));
            // Vérification première ligne du fichier
            if ($ligne == "NOM;PRENOM;MAIL") {
                for ($i = 0; $i < $nbLignes - 1; $i++) {
                    $ligne = fgets($fichier);
                    $ligneDecoupee = explode(";", $ligne);
                    // Création de l'étudiant
                    $etudiant = new Etudiant();
                    $etudiant->setNom($ligneDecoupee[0]);
                    $etudiant->setPrenom($ligneDecoupee[1]);
                    $etudiant->setMail($ligneDecoupee[2]);
                    $etudiant->setEstDemissionaire(false);
                    // Ajout de l'étudiant au groupe
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
        //Utilisé pour pouvoir supprimer un étudiant dans les sous groupe du groupe selectionné
        $enfants = $this->getDoctrine()->getRepository(GroupeEtudiant::class)->children($groupeEtudiant);
        //Récupération du groupe des étudiants non affecté"s pour y ajouter les étudiants supprimés si besoin
        $GroupeDesNonAffectés = $this->getDoctrine()->getRepository(GroupeEtudiant::class)->findOneBySlug("etudiants-non-affectes");
        /* On prépare une variable qui contiendra le groupe à partir duquel ajouter les étudiants. En effet, si le groupe
        est de haut niveau, on ajoute des étudiants depuis le groupe des étudiants non affectés, sinon on ajout des étudiants
        depuis son parent (car dans ce cas, le groupe est un sous groupe) */
        if ($groupeEtudiant->getParent() == null) {
            $groupeAPartirDuquelAjouterEtudiants = $GroupeDesNonAffectés;
        } else {
            $groupeAPartirDuquelAjouterEtudiants = $groupeEtudiant->getParent();
        }
        //On récupère la liste des étudiants ajoutables. Elle contiendra tous les étudiants faisant partie du groupe à partir duquel ajouter, mais
        //ne faisant pas partie du groupe que l'on modifie
        $listeEtudiantsPourAjout = $repoEtud->findAllFromGroupParentButNotCurrent($groupeAPartirDuquelAjouterEtudiants, $groupeEtudiant);
        $estEvaluable = $groupeEtudiant->getEstEvaluable();
        $form = $this->createForm(GroupeEtudiantEditType::class, $groupeEtudiant, ['GroupeAjout' => $listeEtudiantsPourAjout, 'estEvaluable' => $estEvaluable]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($groupeEtudiant->getParent() == null) {
                //Le groupe est de haut niveau alors on ajoute dans le groupe et on supprime du groupe des non affectés
                foreach ($form->get('etudiantsAAjouter')->getData() as $key => $etudiant) {
                    $groupeEtudiant->addEtudiant($etudiant);
                    $GroupeDesNonAffectés->removeEtudiant($etudiant);
                }
                //Le groupe est de haut niveau alors on supprime l'étudiant dans les sous-groupes et dans le groupe puis on l'ajoute dans le groupe des non affectés
                foreach ($form->get('etudiantsASupprimer')->getData() as $key => $etudiant) {
                    foreach ($enfants as $enfant) {
                        $enfant->removeEtudiant($etudiant);
                    }
                    $groupeEtudiant->removeEtudiant($etudiant);
                    $GroupeDesNonAffectés->addEtudiant($etudiant);
                }
            } else {
                //Le groupe n'est pas de haut niveau alors on ajoute juste l'étudiant dans le sous-groupe
                foreach ($form->get('etudiantsAAjouter')->getData() as $key => $etudiant) {
                    $groupeEtudiant->addEtudiant($etudiant);
                }
                //Le groupe n'est pas de haut niveau alors on supprime juste l'étudiant dans le sous-groupe et ses sous-groupes
                foreach ($form->get('etudiantsASupprimer')->getData() as $key => $etudiant) {
                    //On supprime l'étudiant des sous groupes
                    foreach ($enfants as $enfant) {
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
        $groupes = $repo->children($groupeEtudiant); /* On récupère tous les enfants du groupe courant. En effet, on a besoin
                                                      de les traiter un à un pour supprimer les évaluations liées a ceux-ci */

        $groupes[] = $groupeEtudiant; // On ajoute le groupe qu'on supprime à la liste des groupes dont on veut supprimer l'évaluation
        foreach ($groupes as $groupeAModifier) { // Pour tous les enfants du groupe choisi
            foreach ($groupeAModifier->getEvaluations() as $evaluation) { //On récupère toutes les évaluations du groupe courant
                foreach ($evaluation->getParties() as $partie) { //On récupère toutes les parties de l'évaluation courante
                    foreach ($partie->getNotes() as $note) { //On récupère toutes les notes associées aux parties de l'évaluation courante
                        $em->remove($note);
                    }
                    $em->remove($partie);
                }
                $em->remove($evaluation);
            }
        }
        //Si il s'agit d'un groupe de haut niveau, on supprime également les étudiants contenus dans le groupe
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
    public function NewSousFroupe(GroupeEtudiant $groupeEtudiantParent, Request $request): Response
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
