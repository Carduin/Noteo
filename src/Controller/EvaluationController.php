<?php

namespace App\Controller;

use App\Form\PointsType;
use App\Entity\Evaluation;
use App\Entity\Partie;
use App\Entity\Points;
use App\Entity\Statut;
use App\Entity\GroupeEtudiant;
use App\Repository\PartieRepository;
use App\Repository\PointsRepository;
use App\Repository\StatutRepository;
use App\Repository\EvaluationRepository;
use App\Repository\GroupeEtudiantRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\Length;


/**
 * @Route("{_locale}/evaluation")
 */
class EvaluationController extends AbstractController
{
    /**
     * @Route("/mes-evaluations", name="evaluation_enseignant", methods={"GET"})
     */
    public function indexEnseignantConnecte(EvaluationRepository $evaluationRepository): Response
    {
        return $this->render('evaluation/index.html.twig', [
            'evaluations' => $evaluationRepository->findMyEvaluationsWithGradesAndCreatorAndGroup($this->getUser()),
            'mine' => true
        ]);
    }

    /**
     * @Route("/autres-evaluations", name="evaluation_autres", methods={"GET"})
     */
    public function indexAutres(EvaluationRepository $evaluationRepository): Response
    {
        return $this->render('evaluation/index.html.twig', [
            'evaluations' => $evaluationRepository->findOtherEvaluationsWithGradesAndCreatorAndGroup($this->getUser()),
            'mine' => false
        ]);
    }

    /**
     * @Route("/nouvelle/{slug}", name="evaluation_new", methods={"GET","POST"})
     */
    public function new(Request $request, GroupeEtudiant $groupeConcerne): Response
    {
        //Création d'une évaluation vide avec tous ses composants (partie, notes(définies à 0 par défaut))
        $evaluation = new Evaluation();
        $evaluation->setGroupe($groupeConcerne);
        $partie = new Partie();
        $partie->setIntitule("Évaluation");
        $partie->setBareme(20);
        $evaluation->addPartie($partie);
        foreach ($groupeConcerne->getEtudiants() as $etudiant) {
            $note = new Points();
            $note->setValeur(0);
            $etudiant->addPoint($note);
            $partie->addNote($note);
        }
        //Création du formulaire pour saisir les informations de l'évaluation (le formulaire n'est pas lié à une entité)
        $form = $this->createFormBuilder(['notes' => $partie->getNotes()])
            ->add('nom', TextType::class, [
                'constraints' => [
                    new NotBlank,
                    new Length(['max' => 255])
                ]
            ])
            ->add('date', DateType::class, [
                'widget' => 'choice',
                'years' => range(date("Y") - 5, date("Y") + 1),
                'data' => new \DateTime(),
                'constraints' => [new NotBlank, new Date]
            ])
            ->add('notes', CollectionType::class, [
                'entry_type' => PointsType::class //Utilisation d'une collection de formulaire pour saisir les valeurs des notes (les formulaires portent sur les entités points
                //passées en paramètre du formulaire)
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $data = $form->getData(); //Récupération des données du formulaire
            $evaluation->setNom($data["nom"]); // Définition du nom de l'évaluation
            $evaluation->setDate($data["date"]); // -------- de la date -----------
            $evaluation->setEnseignant($this->getUser());
            $evaluation->setNotesSaisies(true);
            $entityManager->persist($evaluation);
            $entityManager->persist($partie);
            foreach ($partie->getNotes() as $note) {
                //Si la note dépasse le barême de la partie, on réduit la note à la valeur du barême
                if ($note->getValeur() > $partie->getBareme()) {
                    $note->setValeur($partie->getBareme());
                }
                if ($note->getValeur() < -1) { // On teste si une valeur inférieure à -1 est rentrée pour ramener la note à 0. -1 est autorisé pour remarquer les absents
                    $note->setValeur(0);
                }
                $entityManager->persist($note);
            }
            $entityManager->flush();
            if ($this->getUser()->getId() == $evaluation->getEnseignant()->getId()) {
                return $this->redirectToRoute('evaluation_enseignant', ['id' => $this->getUser()->getId()]);
            } else {
                return $this->redirectToRoute('evaluation_autres', ['id' => $this->getUser()->getId()]);
            }
        }
        return $this->render('evaluation/new.html.twig', [
            'evaluation' => $evaluation,
            'form' => $form->createView(),
            'parties' => $partie,
            'etudiants' => $evaluation->getGroupe()->getEtudiants()
        ]);
    }

    /**
     * @Route("/nouvelle-avec-parties/{slug}", name="evaluation_avec_parties_new", methods={"GET","POST"})
     */
    public function newAvecParties(Request $request, GroupeEtudiant $groupeConcerne): Response
    {
        //Récupération des informations de l'évaluation
        $formEval = $this->createFormBuilder()
            ->add('nom', TextType::class, [
                'constraints' => [
                    new NotBlank,
                    new Length(['max' => 255]),
                    new Regex(['pattern' => '/[a-zA-Z0-9]/', 'message' => 'Le nom de l\'évaluation doit contenir au moins un chiffre ou une lettre'])
                ],
            ])
            ->add('date', DateType::class, [
                'widget' => 'choice',
                'years' => range(date("Y") - 5, date("Y") + 1),
                'data' => new \DateTime(),
                'constraints' => [new NotBlank, new Date]
            ])
            ->getForm();
        $formEval->handleRequest($request);
        if ($formEval->isSubmitted() && $formEval->isValid()) {
            $data = $formEval->getData(); //Récupération des données du formulaire
            //Mise en session des données de l'évaluation pour la créer à l'action suivante
            $request->getSession()->set('nomEval', $data["nom"]);
            $request->getSession()->set('dateEval', $data["date"]);
            $request->getSession()->set('idGroupeEval', $groupeConcerne->getId());
            $arbreInitial = [ // tableau qui sera utilisé pour initialiser la création des parties à la page suivante
                'id' => 1,
                'text' => $data["nom"],
                'nom' => $data["nom"],
                'bareme' => 20,
                'state' => ['expanded' => true],
                'tags' => ['/20']
            ];
            $request->getSession()->set('arbre_json', $arbreInitial); // Pour récupérer le tableau lors du chargement de la vue de l'action suivante
            return $this->redirectToRoute("creation_parties_eval");
        }
        return $this->render('evaluation_parties/saisie_info_eval_par_parties.html.twig', [
            'form' => $formEval->createView(),
        ]);
    }

    /**
     * @Route("/creation-parties", name="creation_parties_eval", methods={"GET","POST"})
     */
    public function creationParties(Request $request, GroupeEtudiantRepository $repo): Response
    {
        $form = $this->createFormBuilder()
            ->add('arbre', HiddenType::class) // Pour pouvoir stocker le tableau des parties et le récupérer lors de la validation
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $entityManager = $this->getDoctrine()->getManager();
            $data = $form->getData();
            $arbrePartiesRecupere = json_decode(urldecode($data['arbre'])); //Récupération des parties créées par l'utilisateur
            $tableauParties = []; //Initialisation du tableau qui contiendra les parties
            $evaluation = new Evaluation();
            $evaluation->setNom($request->getSession()->get("nomEval"));
            $evaluation->setDate($request->getSession()->get("dateEval"));
            $evaluation->setGroupe($repo->findOneById($request->getSession()->get("idGroupeEval"))); //On refait le lien avec le groupe sinon le manager essaye de le persist lui aussi comme si c'était une nouvelle entité
            $evaluation->setEnseignant($this->getUser());
            $evaluation->setNotesSaisies(false);
            $entityManager->persist($evaluation);
            //récupération des objets Partie depuis l'arborescence créée dans le JSON et mise en base de données
            $this->definirPartiesDepuisTableauJS($evaluation, $arbrePartiesRecupere[0], $tableauParties);
            $tableauParties[0]->setIntitule("Évaluation");
            foreach ($tableauParties as $partie) {
                $entityManager->persist($partie);
            }
            //Creation des entités points correspondant à l'évaluation et toutes ses parties et mise en base de données
            foreach ($evaluation->getGroupe()->getEtudiants() as $etudiant) {
                foreach ($tableauParties as $partie) {
                    $note = new Points();
                    $note->setEtudiant($etudiant);
                    $note->setPartie($partie);
                    $note->setValeur(0);
                    $entityManager->persist($note);
                }
            }
            $entityManager->flush();
            return $this->redirectToRoute('evaluation_edit', [
                'slug' => $evaluation->getSlug()
            ]);
        }
        return $this->render('evaluation_parties/creation_arborescence_parties.html.twig', [
            'form' => $form->createView()
        ]);
    }

    //Cette fonction permet, à partir du tableau récupéré de la vue de création des parties, de remplir un tableau d'objets parties exploitable par la suite
    public function definirPartiesDepuisTableauJS($evaluation, $partieCourante, &$tableauARemplir, $partieParent = null)
    {
        $partie = new Partie();
        if ($partie->getIntitule() == $evaluation->getNom()) {
            $partie->setIntitule("Évaluation");
        } else {
            $partie->setIntitule($partieCourante->nom);
        }
        $partie->setBareme($partieCourante->bareme);
        $partie->setEvaluation($evaluation);
        $partie->setParent($partieParent);
        $tableauARemplir[] = $partie;
        if (isset($partieCourante->nodes)) {
            foreach ($partieCourante->nodes as $enfant) {
                $this->definirPartiesDepuisTableauJS($evaluation, $enfant, $tableauARemplir, $partie);
            }
        }
    }

    /**
     * @Route("/consulter/{slug}", name="evaluation_show", methods={"GET"})
     */
    public function show(Evaluation $evaluation, PointsRepository $repoPoints): Response
    {
        $notes = $repoPoints->findAllByEvaluation($evaluation->getId());
        return $this->render('evaluation/show.html.twig', [
            'evaluation' => $evaluation,
            'notes' => $notes
        ]);
    }

    /**
     * @Route("/modifier/{slug}", name="evaluation_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Evaluation $evaluation, PartieRepository $repoPartie, PointsRepository $repoPoints): Response
    {
        $this->denyAccessUnlessGranted('EVALUATION_EDIT', $evaluation);
        $partiesASaisir = $repoPartie->findLowestPartiesByEvaluationIdWithGrades($evaluation->getId());
        $notes = $repoPoints->findAllFromLowestParties($evaluation->getId());
        $form = $this->createFormBuilder(['notes' => $notes])
            ->add('nom', TextType::class, [
                'data' => $evaluation->getNom(),
                'constraints' => [
                    new Regex(['pattern' => '/[a-zA-Z0-9]/', 'message' => 'Le nom de l\'évaluation doit contenir au moins un chiffre ou une lettre']),
                    new NotBlank(),
                    new Length(['max' => 255]),
                ]
            ])
            ->add('date', DateType::class, [
                'widget' => 'choice',
                'years' => range(date("Y") - 5, date("Y") + 1),
                'data' => $evaluation->getDate(),
                'constraints' => [new NotBlank, new Date]
            ])
            ->add('notes', CollectionType::class, [
                'entry_type' => PointsType::class, //Utilisation d'une collection de formulaire pour saisir les valeurs des notes (les formulaires portent sur les entités points
                //passées en paramètre du formulaire)
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $data = $form->getData();
            $evaluation->setNom($data["nom"]);
            $evaluation->setDate($data["date"]);
            $evaluation->setNotesSaisies(true);
            $entityManager->persist($evaluation);
            $notes = $data['notes'];
            foreach ($notes as $note) {
                $entityManager->persist($note);
            }
            //Calcul des notes supérieures
            //On récupère les parties dont la note n'a pas été calculée (celles qui ont au moins une sous-partie). Les parties sont organisées des plus basses aux plus hautes
            $partiesACalculer = $repoPartie->findHighestByEvaluation($evaluation->getId());
            foreach ($evaluation->getGroupe()->getEtudiants() as $etudiant) {
                foreach ($partiesACalculer as $partie) {
                    $sommePtsSousPartie = 0;
                    $sousParties = $partie->getChildren();
                    $etudiantAbsent = true; //On suppose que l'étudiant est absent à cette partie sauf si on trouve une note supérieure à -1 dans les sous parties (il peut avoir manqué seulement une partie de l'évaluation ainsi)
                    //On fait la somme des notes obtenues aux sous parties
                    foreach ($sousParties as $sousPartie) {
                        $point = $repoPoints->findByPartieAndByStudent($sousPartie->getId(), $etudiant->getId());
                        //On ne prend pas en compte -1 dans le calcul total
                        if ($point->getValeur() >= 0) {
                            $sommePtsSousPartie += $point->getValeur();
                            $etudiantAbsent = false;
                        }
                    }
                    $point = $repoPoints->findByPartieAndByStudent($partie->getId(), $etudiant->getId());
                    //Si la note est inférieure à 0 c'est que l'étudiant était absent
                    if ($etudiantAbsent) {
                        $point->setValeur(-1);
                    } else {
                        $point->setValeur($sommePtsSousPartie);
                    }
                    $entityManager->persist($point);
                }
            }
            $entityManager->flush();
            return $this->redirectToRoute('evaluation_enseignant');
        }
        return $this->render('evaluation/edit.html.twig', [
            'evaluation' => $evaluation,
            'form' => $form->createView(),
            'parties' => $partiesASaisir,
            'etudiants' => $evaluation->getGroupe()->getEtudiants(),
        ]);
    }

    /**
     * @Route("/supprimer/{slug}", name="evaluation_delete", methods={"GET"})
     */
    public function delete(Request $request, Evaluation $evaluation): Response
    {
        $this->denyAccessUnlessGranted('EVALUATION_DELETE', $evaluation);
        $entityManager = $this->getDoctrine()->getManager();
        //Suppression des parties associées à l'évaluation
        foreach ($evaluation->getParties() as $partie) {
            //Suppression des notes associées à la partie
            foreach ($partie->getNotes() as $note) {
                $entityManager->remove($note);
            }
            $entityManager->remove($partie);
        }
        $entityManager->remove($evaluation);
        $entityManager->flush();
        if ($this->getUser()->getId() == $evaluation->getEnseignant()->getId()) {
            return $this->redirectToRoute('evaluation_enseignant', ['id' => $this->getUser()->getId()]);
        } else {
            return $this->redirectToRoute('evaluation_autres', ['id' => $this->getUser()->getId()]);
        }
    }

    /**
     * @Route("/{typeEval}/choisir-groupe/", name="evaluation_choose_group")
     */
    public function choixGroupeEval(Request $request, GroupeEtudiantRepository $repoGroupe, $typeEval): Response
    {
        $groupes = $repoGroupe->findAllWithoutNonEvaluableGroups();
        $form = $this->createFormBuilder()
            ->add('groupes', EntityType::class, [
                'constraints' => [new NotNull],
                'class' => GroupeEtudiant::Class,
                'choice_label' => false,
                'label' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => false,
                'choices' => $groupes
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            //En fonction du type d'évaluation correct on renvoie sur la bonne route avec le groupe choisi
            switch ($typeEval) {
                case 'simple' :
                    return $this->redirectToRoute('evaluation_new', ['slug' => $form->get("groupes")->getData()->getSlug()]);
                    break;
                case 'avec-parties' :
                    return $this->redirectToRoute('evaluation_avec_parties_new', ['slug' => $form->get("groupes")->getData()->getSlug()]);
                    break;
            }
        }
        return $this->render('evaluation/choix_groupe.html.twig', ['groupes' => $groupes, 'form' => $form->createView()]);
    }
}
