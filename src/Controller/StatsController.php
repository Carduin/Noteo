<?php

namespace App\Controller;

use App\Entity\Evaluation;
use App\Entity\GroupeEtudiant;
use App\Entity\Etudiant;
use App\Entity\Partie;
use App\Entity\Statut;
use App\Manager\StatisticsManager;
use App\Repository\EvaluationRepository;
use App\Repository\GroupeEtudiantRepository;
use App\Repository\EtudiantRepository;
use App\Repository\PointsRepository;
use App\Repository\StatutRepository;
use Swift_Attachment;
use Swift_Mailer;
use Swift_Message;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @Route("/statistiques")
 */
class StatsController extends AbstractController
{
    /**
     * @Route("/", name="statistiques", methods={"GET"})
     */
    public function choixStatistiques(EvaluationRepository $repoEval, StatutRepository $repoStatut, GroupeEtudiantRepository $repoGroupe, EtudiantRepository $repoEtudiant): Response
    {
        //Ce tableau est utilisé dans la vue pour déterminer si un type de stat est disponible ou non, et si non pourquoi
        //Le tableau contient un tableau par type de stats. Le tableau correspondant à un type de stats contient un booléen indiquant
        //si la statistique est disponible et, si le critère de disponibilité est double (par exemple si pour qu'un type de stats
        //soit disponible il faut plusieurs groupes ET plusieurs évals, contient le nombre de chaque critère pour pouvoir indiquer
        //à l'utilisateur ce qu'il manque (des groupes ou des évals ou les deux)
        $nombreEvalsSimples = count($repoEval->findAllWithOnePart());
        $nombreEvalsAvecParties = count($repoEval->findAllWithSeveralParts());
        $nombreEvaluationsTotal = count($repoEval->findAll());
        $nombreGroupes = count($repoGroupe->findAllHavingStudents());
        $nombreStatuts = count($repoStatut->findAllHavingStudents());
        $nombreEtudiantsConcerneParUneEvalOuPlus = count($repoEtudiant->findAllParticipatedAtLeastOneEvaluation());
        $statsDispo = [
            "evalSimple" => [
                "disponible" => $nombreEvalsSimples >= 1
            ],
            "evalParties" => [
                "disponible" => $nombreEvalsAvecParties >= 1
            ],
            "plusieursEvalsGroupes" => [
                "disponible" => $nombreGroupes >= 1 && $nombreEvaluationsTotal >= 2,
                "nombreGroupes" => $nombreGroupes,
                "nombreEvals" => $nombreEvaluationsTotal
            ],
            "plusieursEvalsStatuts" => [
                "disponible" => $nombreStatuts >= 1 && $nombreEvaluationsTotal >= 2,
                "nombreStatuts" => $nombreStatuts,
                "nombreEvals" => $nombreEvaluationsTotal
            ],
            "ficheEtudiant" => [
                "disponible" => $nombreEtudiantsConcerneParUneEvalOuPlus >= 1 && $nombreEvaluationsTotal >= 2,
                "nombreEtudiants" => $nombreEtudiantsConcerneParUneEvalOuPlus,
                "nombreEvals" => $nombreEvaluationsTotal
            ],
            "comparaison" => [
                "disponible" => $nombreEvaluationsTotal >= 2
            ]
        ];
        return $this->render('statistiques/statistiques_disponibles.html.twig', [
            "statistiques" => $statsDispo
        ]);
    }

    ///////////////////////
    ///STATS EVAL SIMPLE///
    ///////////////////////

    /**
     * @Route("/eval-simple/{typeGraphique}/choisir-evaluation", name="eval_simple_choisir_evaluation", methods={"GET", "POST"})
     */
    public function evalSimpleChoisirEvaluation($typeGraphique, EvaluationRepository $repoEval, Request $request) : Response
    {
        $request->getSession()->set('typeGraphique', $typeGraphique);
        $form = $this->createFormBuilder()
            ->add('evaluations', EntityType::class, [
                'constraints' => [new NotNull],
                'class' => Evaluation::Class,
                'choice_label' => false,
                'label' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => false,
                'choices' => $repoEval->findAllWithOnePart()
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
             return $this->redirectToRoute('eval_simple_choisir_parametres_et_afficher_stats', [
                'slug' => $form->get('evaluations')->getData()->getSlug()
             ]);
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 1,
            'titrePage' => 'Analyse d’une évaluation simple',
            'activerToutSelectionner' => false,
            'colorationEffectif' => false,
            'casBoutonValider' => 0,
            'typeForm1' => 'evaluations',
            'conditionAffichageForm1' => true,
            'sousTitreForm1' => 'Choisir l\'évaluation pour laquelle vous désirez consulter les statistiques',
        ]);
    }

    /**
     * @Route("/eval-simple/{slug}/choisir-groupes-et-statuts", name="eval_simple_choisir_parametres_et_afficher_stats", methods={"GET","POST"})
     */
    public function evalSimpleChoisirParametresEtAfficherStats(Request $request, StatisticsManager $statsManager, Evaluation $evaluation, StatutRepository $repoStatut, GroupeEtudiantRepository $repoGroupe): Response
    {
        $formBuilder = $this->createFormBuilder();
        $statuts = $repoStatut->findByEvaluation($evaluation->getId()); // On choisira parmis les statuts qui possèdent au moins 1 étudiant ayant participé à l'évaluation
        if (count($evaluation->getParties()) > 1) {
            $formBuilder
                ->add('parties', EntityType::class, [
                    'class' => Partie::Class,
                    'choice_label' => false,
                    'label' => false,
                    'mapped' => false,
                    'expanded' => true,
                    'multiple' => true,
                    'choices' => $evaluation->getParties()
                ]);
        }
        $formBuilder
            ->add('groupes', EntityType::class, [
                'class' => GroupeEtudiant::Class,
                'choice_label' => false,
                'label' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $repoGroupe->findAllOrderedFromNode($evaluation->getGroupe()) // On choisira parmis le groupe concerné et ses enfants
            ])
            ->add('statuts', EntityType::class, [
                'class' => Statut::Class,
                'choice_label' => false,
                'label' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' =>  $statuts
            ]);
        $form = $formBuilder->getForm()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $groupesChoisis = $form->get("groupes")->getData();
            $statutsChoisis = $form->get("statuts")->getData();
            $statistiquesCalculees = $statsManager->calculerStatsClassiques($evaluation, $groupesChoisis, $statutsChoisis, $evaluation->getParties());
            $request->getSession()->set('stats', $statistiquesCalculees);
            //Pour ne pas continuer si les conditions ne sont pas remplies (au moins un groupe ou statut)
            if (count($groupesChoisis) > 0 || count($statutsChoisis) > 0) {
                return $this->render('statistiques/affichage_stats_classiques.html.twig', [
                    'titrePage' => 'Statistiques pour ' . $evaluation->getNom(),
                    'plusieursEvals' => false,
                    'evaluation' => $evaluation,
                    'parties' => $statistiquesCalculees
                ]);
            }
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 2,
            'activerToutSelectionner' => true,
            'titrePage' => "Analyse d’une évaluation simple (" . $evaluation->getNom() . ")",
            'colorationEffectif' => false,
            'casBoutonValider' => 1,
            'typeForm1' => 'groupes',
            'affichageEffectifParStatut' => false,
            'sousTitreForm1' => 'Sélectionner les groupes pour lesquels vous souhaitez consulter les statistiques',
            'conditionAffichageForm1' => true,
            'indentationGroupes' => true,
            'typeForm2' => 'statuts',
            'sousTitreForm2' => 'Sélectionner les groupes d\'étudiants ayant un statut particulier pour lesquels vous souhaitez consulter les statistiques',
            'conditionAffichageForm2' => !empty($statuts),
            'messageAlternatifForm2' => 'Il est possible d\'obtenir des statistiques sur des groupes d\'étudiantsayant un statut particulier (boursiers, redoublants, ...). Vous pouvez créer de tels groupes <a href="' . $this->generateUrl('statut_new') . '">ici</a>.'
        ]);
    }

    ///////////////////////
    ////FIN EVAL SIMPLE////
    ///////////////////////

    ///////////////////////
    ///STATS EVAL PARTIE///
    ///////////////////////

    /**
     * @Route("/eval-parties/{typeGraphique}/choisir-evaluation", name="eval_parties_choisir_evaluation", methods={"GET", "POST"})
     */
    public function evalPartiesChoisirEvaluation($typeGraphique, EvaluationRepository $repoEval, Request $request) : Response
    {
        $request->getSession()->set('typeGraphique', $typeGraphique);
        $form = $this->createFormBuilder()
            ->add('evaluations', EntityType::class, [
                'constraints' => [new NotNull],
                'class' => Evaluation::Class,
                'choice_label' => false,
                'label' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => false,
                'choices' => $repoEval->findAllWithSeveralParts()
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return $this->redirectToRoute('eval_parties_choisir_parametres_et_afficher_stats', [
                'slug' => $form->get('evaluations')->getData()->getSlug()
            ]);
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 1,
            'titrePage' => 'Analyse d’une évaluation avec parties',
            'activerToutSelectionner' => false,
            'colorationEffectif' => false,
            'casBoutonValider' => 0,
            'typeForm1' => 'evaluations',
            'conditionAffichageForm1' => true,
            'sousTitreForm1' => 'Choisir l\'évaluation pour laquelle vous désirez consulter les statistiques',
        ]);
    }

    /**
     * @Route("/eval-parties/{slug}/choisir-groupes-et-statuts", name="eval_parties_choisir_parametres_et_afficher_stats", methods={"GET","POST"})
     */
    public function evalPartieschoisirParametresEtAfficherStats(Request $request, StatisticsManager $statsManager, Evaluation $evaluation, StatutRepository $repoStatut, GroupeEtudiantRepository $repoGroupe): Response
    {
        $formBuilder = $this->createFormBuilder();
        $statuts = $repoStatut->findByEvaluation($evaluation->getId()); // On choisira parmis les statuts qui possèdent au moins 1 étudiant ayant participé à l'évaluation
            $formBuilder
            ->add('parties', EntityType::class, [
                'class' => Partie::Class,
                'choice_label' => false,
                'label' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $evaluation->getParties()
            ])
            ->add('groupes', EntityType::class, [
                'class' => GroupeEtudiant::Class,
                'choice_label' => false,
                'label' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $repoGroupe->findAllOrderedFromNode($evaluation->getGroupe()) // On choisira parmis le groupe concerné et ses enfants
            ])
            ->add('statuts', EntityType::class, [
                'class' => Statut::Class,
                'choice_label' => false,
                'label' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' =>  $statuts
            ]);
        $form = $formBuilder->getForm()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $groupesChoisis = $form->get("groupes")->getData();
            $statutsChoisis = $form->get("statuts")->getData();
            $partiesChoisies = $form->get("parties")->getData();
            $statistiquesCalculees = $statsManager->calculerStatsClassiques($evaluation, $groupesChoisis, $statutsChoisis, $partiesChoisies);
            $request->getSession()->set('stats', $statistiquesCalculees);
            //Pour ne pas continuer si les conditions ne sont pas remplies (au moins un groupe ou statut)
            if ((count($groupesChoisis) > 0 || count($statutsChoisis) > 0) && count($partiesChoisies) > 0) {
                return $this->render('statistiques/affichage_stats_classiques.html.twig', [
                    'titrePage' => 'Statistiques pour ' . $evaluation->getNom(),
                    'plusieursEvals' => false,
                    'evaluation' => $evaluation,
                    'parties' => $statistiquesCalculees
                ]);
            }
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 3,
            'activerToutSelectionner' => true,
            'titrePage' => "Analyse d’une évaluation avec parties (" . $evaluation->getNom() . ")",
            'colorationEffectif' => false,
            'casBoutonValider' => 2,
            'typeForm1' => 'parties',
            'sousTitreForm1' => 'Sélectionner au moins une partie de l\'évaluation pour laquelle vous souhaitez consulter les statistiques',
            'conditionAffichageForm1' => true,
            'typeForm2' => 'groupes',
            'affichageEffectifParStatut' => false,
            'sousTitreForm2' => 'Sélectionner les groupes pour lesquels vous souhaitez consulter les statistiques',
            'conditionAffichageForm2' => true,
            'indentationGroupes' => true,
            'typeForm3' => 'statuts',
            'sousTitreForm3' => 'Sélectionner les groupes d\'étudiants ayant un statut particulier pour lesquels vous souhaitez consulter les statistiques',
            'conditionAffichageForm3' => !empty($statuts),
            'messageAlternatifForm3' => 'Il est possible d\'obtenir des statistiques sur des groupes d\'étudiantsayant un statut particulier (boursiers, redoublants, ...). Vous pouvez créer de tels groupes <a href="' . $this->generateUrl('statut_new') . '">ici</a>.'
        ]);
    }

    ///////////////////////
    ////FIN EVAL PARTIE////
    ///////////////////////

    ///////////////////////////////
    //STATS PLUSIEURS EVAL GROUPE//
    ///////////////////////////////

    /**
     * @Route("/plusieurs-evaluations/groupes/{typeGraphique}/choisir-groupe", name="plusieurs_evaluations_groupes_choisir_haut_niveau")
     */
    public function plusieursEvaluationsGroupesChoisirGroupe(Request $request, $typeGraphique, GroupeEtudiantRepository $repoGroupe): Response
    {
        $request->getSession()->set('typeGraphique', $typeGraphique);
        $choices = $repoGroupe->findHighestEvaluableWith1EvalOrMore();
        $form = $this->createFormBuilder()
            ->add('groupes', EntityType::class, [
                'class' => GroupeEtudiant::Class,
                'constraints' => [new NotBlank()],
                'choice_label' => false,
                'label' => false,
                'expanded' => true,
                'multiple' => false,
                'choices' => $choices // On choisira parmis les groupes de plus haut niveau évaluables qui ont au moins 1 évaluation que les concernent
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return $this->redirectToRoute('plusieurs_evaluations_groupes_choisir_sous_groupes', [
                'slug' => $form->get('groupes')->getData()->getSlug()
            ]);
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 1,
            'colorationEffectif' => false,
            'indentationGroupes' => false,
            'casBoutonValider' => 0,
            'activerToutSelectionner' => false,
            'titrePage' => 'Analyse des résultats de groupe(s) d’étudiant(s) sur plusieurs évaluations',
            'typeForm1' => 'groupes',
            'sousTitreForm1' => 'Sélectionner le groupe pour lequel vous souhaitez voir des statistiques',
            'conditionAffichageForm1' => true,
            'affichageEffectifParStatut' => false
            ]);
    }

    /**
     * @Route("/plusieurs-evaluations/groupes/{slug}/choisir-sous-groupes", name="plusieurs_evaluations_groupes_choisir_sous_groupes")
     */
    public function plusieursEvaluationsGroupesChoisirSousGroupes(Request $request, GroupeEtudiant $groupe, GroupeEtudiantRepository $repoGroupe): Response
    {
        $session = $request->getSession();
        $typeGraph = $request->getSession()->get('typeGraphique');
        $sousGroupes = $repoGroupe->findAllOrderedFromNode($groupe);
        $form = $this->createFormBuilder()
            ->add('groupes', EntityType::class, [
                'constraints' => [
                    new NotBlank()
                ],
                'class' => GroupeEtudiant::Class,
                'choice_label' => false,
                'label' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $sousGroupes
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (count($form->get('groupes')->getData()) > 0) {
                $sousGroupes = $form->get('groupes')->getData();
                $request->getSession()->set('sousGroupes', $sousGroupes);
                return $this->redirectToRoute('plusieurs_evaluations_groupes_choisir_evaluations', [
                    'slug' => $groupe->getSlug()
                ]);
            }
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 1,
            'colorationEffectif' => false,
            'indentationGroupes' => true,
            'casBoutonValider' => 3,
            'activerToutSelectionner' => true,
            'titrePage' => 'Analyse des résultats de groupe(s) d’étudiant(s) sur plusieurs évaluations',
            'typeForm1' => 'groupes',
            'sousTitreForm1' => 'Sélectionner les sous-groupes de ' . $groupe->getNom() . ' pour lesquels vous souhaitez voir des statistiques',
            'conditionAffichageForm1' => true,
            'affichageEffectifParStatut' => false
            ]);
    }

    /**
     * @Route("/plusieurs-evaluations/groupes/{slug}/choisir-evaluations", name="plusieurs_evaluations_groupes_choisir_evaluations")
     */
    public function plusieursEvaluationsGroupesChoisirEvals(Request $request, EvaluationRepository $repoEval, GroupeEtudiantRepository $repoGroupe, StatisticsManager $statsManager, GroupeEtudiant $groupe): Response
    {
        $session = $request->getSession();
        $typeGraph = $session->get('typeGraphique');
        $form = $this->createFormBuilder()
            ->add('evaluations', EntityType::class, [
                'class' => Evaluation::Class,
                'choice_label' => false,
                'label' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $groupe->getEvaluations()
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $evaluations = $form->get('evaluations')->getData();
            $listeStatsParGroupe = array(); // On initialise un tableau vide qui contiendra les statistiques des groupes choisis
            $lesGroupes = array(); // On regroupe le groupe principal et les sous groupes pour faciliter la requete
            foreach ($request->getSession()->get('sousGroupes') as $sousGroupe) {
                array_push($lesGroupes, $sousGroupe);
            }
            return $this->render('statistiques/affichage_stats_classiques.html.twig', [
                'parties' => $statsManager->calculerStatsPlusieursEvals('groupes', $lesGroupes, $evaluations),
                'evaluations' => $evaluations,
                'groupes' => $lesGroupes,
                'titrePage' => 'Consulter les statistiques sur ' . count($evaluations) . ' évaluation(s)',
                'plusieursEvals' => true
                ]);
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 1,
            'titrePage' => 'Analyse des résultats de groupe(s) d’étudiant(s) sur plusieurs évaluations',
            'activerToutSelectionner' => true,
            'typeForm1' => 'evaluations',
            'sousTitreForm1' => 'Sélectionner les évaluations pour lesquelles vous souhaitez voir des statistiques pour les groupes précédemment sélectionnés',
            'conditionAffichageForm1' => true,
            'casBoutonValider' => 4
        ]);
    }

    ///////////////////////////////////
    //FIN STATS PLUSIEURS EVAL GROUPE//
    ///////////////////////////////////

    ////////////////////////////////
    //STATS PLUSIEURS EVAL STATUTS//
    ////////////////////////////////

    /**
     * @Route("/plusieurs-eval/statuts/{typeGraphique}/choisir-statut", name="plusieurs_evaluations_statut_choisir_statut")
     */
    public function plusieursEvaluationsStatutChoisirStatutsEvaluable(Request $request, StatutRepository $repoStatut, $typeGraphique): Response
    {
        $session = $request->getSession();
        //On met en sesssion le type de graphique choisi par l'utilisateur pour afficher l'onglet correspondant lors de l'affichage des stats
        $request->getSession()->set('typeGraphique', $typeGraphique);
        $form = $this->createFormBuilder()
            ->add('groupes', EntityType::class, [
                'class' => Statut::Class, //On veut choisir des statut
                'constraints' => [new NotBlank()],
                'choice_label' => false, // On n'affichera pas d'attribut de l'entité à côté du bouton pour aider au choix car on liste les entités en utilisant les variables du champ
                'label' => false, // On n'affiche pas le label du champ
                'expanded' => true, // Pour avoir des boutons
                'multiple' => false,
                'choices' => $repoStatut->findAllWith1EvalOrMore() // On choisira parmis les statut de plus haut niveau évaluables qui ont au moins 1 évaluation que les concernent
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return $this->redirectToRoute('plusieurs_evaluations_statut_choisir_evaluations', [
                'slug' => $form->get('groupes')->getData()->getSlug()
            ]);
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 1,
            'casBoutonValider' => 0,
            'activerToutSelectionner' => false,
            'titrePage' => 'Analyse des résultats d’un statut d’étudiants sur plusieurs évaluations',
            'typeForm1' => 'groupes',
            'colorationEffectif' => false,
            'indentationGroupes' => false,
            'affichageEffectifParStatut' => false,
            'sousTitreForm1' => 'Sélectionner le groupe d\'étudiants ayant un statut particulier pour lequel vous souhaitez voir des statistiques',
            'conditionAffichageForm1' => true,
        ]);
    }

    /**
     * @Route("/plusieurs-eval/statuts/{slug}/choisir-evaluations", name="plusieurs_evaluations_statut_choisir_evaluations")
     */
    public function plusieursEvaluationsStatutChoisirEvaluations(Request $request, StatisticsManager $statsManager, Statut $statut, EvaluationRepository $repoEval, PointsRepository $repoPoints): Response
    {
        $form = $this->createFormBuilder()
            ->add('evaluations', EntityType::class, [
                'class' => Evaluation::Class, //On veut choisir des evaluations
                'constraints' => [
                    new NotBlank()
                ],
                'choice_label' => false, // On n'affichera pas d'attribut de l'entité à côté du bouton pour aider au choix car on liste les entités en utilisant les variables du champ
                'label' => false, // On n'affiche pas le label du champ
                'expanded' => true, // Pour avoir des cases
                'multiple' => true, // à cocher
                'choices' => $repoEval->findAllByStatut($statut->getId()) // On choisira parmis les evaluations du groupe principal
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $evaluations = array();
            if (count($form->get('evaluations')->getData()) > 0) {
                $evaluations = $form->get('evaluations')->getData();
            }
            return $this->render('statistiques/affichage_stats_classiques.html.twig', [
                'parties' => $statsManager->calculerStatsPlusieursEvals('statuts', [$statut], $evaluations),
                'evaluations' => $evaluations,
                'groupes' => $statut,
                'titrePage' => 'Consulter les statistiques sur ' . count($evaluations) . ' évaluation(s)',
                'plusieursEvals' => true,
            ]);
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 1,
            'titrePage' => 'Analyse des résultats d’un statut d’étudiants sur plusieurs évaluations (' . $statut->getNom() . ')',
            'activerToutSelectionner' => true,
            'typeForm1' => 'evaluations',
            'sousTitreForm1' => 'Sélectionner les évaluations pour lesquelles vous souhaitez voir des statistiques',
            'conditionAffichageForm1' => true,
            'casBoutonValider' => 4
        ]);
    }

    ////////////////////////////////////
    //FIN STATS PLUSIEURS EVAL STATUTS//
    ////////////////////////////////////

    ////////////////////////
    //STATS FICHE ETUDIANT//
    ////////////////////////

    /**
     * @Route("/fiche-etudiant/choisir-etudiant", name="fiche_etudiant_choisir_etudiant")
     */
    public function ficheEtudiantChoisirEtudiant(Request $request, StatisticsManager $statsManager, EvaluationRepository $repoEval, EtudiantRepository $repoEtudiant): Response
    {
        $form = $this->createFormBuilder()
            ->add('etudiants', EntityType::class, [
                'class' => Etudiant::Class, //On veut choisir un étudiant
                'choice_label' => false, // On n'affichera pas d'attribut de l'entité à côté du bouton pour aider au choix car on liste les entités en utilisant les variables du champ
                'label' => false, // On n'affiche pas le label du champ
                'expanded' => true, // Pour avoir des boutons
                'multiple' => false, // radios
                'choices' => $repoEtudiant->findAllParticipatedAtLeastOneEvaluation(), // On choisira parmis tous les étudiants
                'constraints' => [new NotBlank()]
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $etudiant = $form->get('etudiants')->getData();
            $evaluations = $repoEval->findAllByEtudiant($etudiant->getId());
            $groupesEtStatuts = array();
            $groupes = array();
            $statuts = array();
            foreach ($etudiant->getGroupes() as $groupe) {
                if ($groupe->getEstEvaluable() == true) {
                    array_push($groupes, $groupe);
                }
            }
            foreach ($etudiant->getStatuts() as $statut) {
                array_push($groupesEtStatuts, $statut);
                array_push($statuts, $statut);
            }

            return $this->render('statistiques/affichage_stats_fiche_etudiant.html.twig', [
                'etudiant' => $etudiant,
                'evaluations' => $evaluations,
                'groupesEtStatuts' => $groupesEtStatuts,
                'stats' => $statsManager->calculerStatsFicheEtudiant($etudiant, $evaluations, $groupes, $statuts),
                'titre' => 'Fiche de l\'étudiant ' . $etudiant->getPrenom() . ' ' . $etudiant->getNom()
            ]);
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 1,
            'titrePage' => 'Évolution chronologique des résultats d’un étudiant',
            'activerToutSelectionner' => false,
            'colorationEffectif' => false,
            'casBoutonValider' => 0,
            'typeForm1' => 'etudiants',
            'sousTitreForm1' => 'Sélectionner l\'étudiant pour consulter sa fiche',
            'conditionAffichageForm1' => true,
        ]);
    }

    /////////////////////////////
    //FIN STATS FICHE ETUDIANT //
    /////////////////////////////

    //////////////////////////
    //STATS EVOLUTION GROUPE//
    //////////////////////////

    /**
     * @Route("/evolution/groupes/choisir-groupe", name="evolution_groupes_choisir_haut_niveau")
     */
    public function evolutionGroupesChoisirGroupe(Request $request, GroupeEtudiantRepository $repoGroupe): Response
    {
        $session = $request->getSession();
        $choices = $repoGroupe->findHighestEvaluableWith1EvalOrMore();
        $form = $this->createFormBuilder()
            ->add('groupes', EntityType::class, [
                'class' => GroupeEtudiant::Class,
                'constraints' => [new NotBlank()],
                'choice_label' => false,
                'label' => false,
                'expanded' => true,
                'multiple' => false,
                'choices' => $choices // On choisira parmis les groupes de plus haut niveau évaluables qui ont au moins 1 évaluation que les concernent
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return $this->redirectToRoute('evolution_groupes_choisir_sous_groupes', [
                'slug' => $form->get('groupes')->getData()->getSlug()
            ]);
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 1,
            'colorationEffectif' => true,
            'indentationGroupes' => false,
            'casBoutonValider' => 0,
            'activerToutSelectionner' => false,
            'titrePage' => 'Évolution chronologique des résultats d’un ensemble d’étudiants',
            'typeForm1' => 'groupes',
            'sousTitreForm1' => 'Sélectionner le groupe pour lequel vous souhaitez voir des statistiques',
            'conditionAffichageForm1' => true,
            'affichageEffectifParStatut' => false,
            'messageWarningForm1' => 'La couleur de l\'effectif d\'un groupe indique la facilité de lecture du graphique qui sera généré pour ce groupe.'
        ]);
    }

    /**
     * @Route("/evolution/groupes/{slug}/choisir-sous-groupes", name="evolution_groupes_choisir_sous_groupes")
     */
    public function evolutionGroupesChoisirSousGroupes(Request $request, GroupeEtudiant $groupe, GroupeEtudiantRepository $repoGroupe): Response
    {
        $session = $request->getSession();
        $typeGraph = $request->getSession()->get('typeGraphique');
        $sousGroupes = $repoGroupe->findAllOrderedFromNode($groupe);
        $form = $this->createFormBuilder()
            ->add('groupes', EntityType::class, [
                'constraints' => [
                    new NotBlank()
                ],
                'class' => GroupeEtudiant::Class,
                'choice_label' => false,
                'label' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $sousGroupes
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (count($form->get('groupes')->getData()) > 0) {
                $sousGroupes = $form->get('groupes')->getData();
                $request->getSession()->set('sousGroupes', $sousGroupes);
                return $this->redirectToRoute('evolution_groupes_choisir_evaluations', [
                    'slug' => $groupe->getSlug()
                ]);
            }
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 1,
            'colorationEffectif' => true,
            'indentationGroupes' => true,
            'casBoutonValider' => 3,
            'activerToutSelectionner' => true,
            'titrePage' => 'Évolution chronologique des résultats d’un ensemble d’étudiants',
            'typeForm1' => 'groupes',
            'sousTitreForm1' => 'Sélectionner les sous-groupes de ' . $groupe->getNom() . ' pour lesquels vous souhaitez voir des statistiques',
            'conditionAffichageForm1' => true,
            'affichageEffectifParStatut' => false,
            'messageWarningForm1' => 'La couleur de l\'effectif d\'un groupe indique la facilité de lecture du graphique qui sera généré pour ce groupe.'
        ]);
    }

    /**
     * @Route("/evolution/groupes/{slug}/choisir-evaluations", name="evolution_groupes_choisir_evaluations")
     */
    public function evolutionGroupesChoisirEvals(Request $request, EvaluationRepository $repoEval, GroupeEtudiantRepository $repoGroupe, StatisticsManager $statsManager, GroupeEtudiant $groupe): Response
    {
        $session = $request->getSession();
        $typeGraph = $session->get('typeGraphique');
        $form = $this->createFormBuilder()
            ->add('evaluations', EntityType::class, [
                'class' => Evaluation::Class,
                'choice_label' => false,
                'label' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $groupe->getEvaluations()
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $evaluations = $form->get('evaluations')->getData();
            $evaluations = $session->get('evaluations'); // récupération des évaluations passées en session
            $tabEvaluations = array();
            foreach ($evaluations as $evaluation) {
                array_push($tabEvaluations, $repoEval->find($evaluation->getId()));
            }
            $lesGroupes = []; // On regroupe le groupe principal et les sous groupes pour faciliter la requete
            foreach ($request->getSession()->get('sousGroupes') as $sousGroupe) {
                array_push($lesGroupes, $repoGroupe->find($sousGroupe->getId()));
            }
            //Pour avoir les évaluations ordonnées chronologiquement
            usort($tabEvaluations, function ($a, $b) {
                if ($a->getdate() == $b->getdate()) {
                    return 0;
                }
                return ($a->getdate() < $b->getdate()) ? -1 : 1;
            });
            return $this->render('statistiques/affichage_stats_evolution.html.twig', [
                'evaluations' => $tabEvaluations,
                'groupes' => $lesGroupes,
                'titre' => 'Évolution chronologique des résultats d’un ensemble d’étudiants',
                'stats' => $statsManager->calculerStatsEvolution('groupe', $lesGroupes, $tabEvaluations)
            ]);
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 1,
            'titrePage' => 'Évolution chronologique des résultats d’un ensemble d’étudiants',
            'activerToutSelectionner' => true,
            'typeForm1' => 'evaluations',
            'sousTitreForm1' => 'Sélectionner les évaluations pour lesquelles vous souhaitez voir des statistiques pour les groupes précédemment sélectionnés',
            'conditionAffichageForm1' => true,
            'casBoutonValider' => 4
        ]);
    }

    //////////////////////////////
    //FIN STATS EVOLUTION GROUPE//
    //////////////////////////////

    ///////////////////////////////
    ////STATS EVOLUTION STATUTS////
    ///////////////////////////////

    /**
     * @Route("/evolution/statuts/choisir-statut", name="evolution_statut_choisir_statut")
     */
    public function evolutionStatutChoisirStatut(Request $request, StatutRepository $repoStatut): Response
    {
        $session = $request->getSession();
        $form = $this->createFormBuilder()
            ->add('groupes', EntityType::class, [
                'class' => Statut::Class, //On veut choisir des statut
                'constraints' => [new NotBlank()],
                'choice_label' => false, // On n'affichera pas d'attribut de l'entité à côté du bouton pour aider au choix car on liste les entités en utilisant les variables du champ
                'label' => false, // On n'affiche pas le label du champ
                'expanded' => true, // Pour avoir des boutons
                'multiple' => false,
                'choices' => $repoStatut->findAllWith1EvalOrMore() // On choisira parmis les statut de plus haut niveau évaluables qui ont au moins 1 évaluation que les concernent
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $session->set('statut', $form->get('groupes')->getData());
            return $this->redirectToRoute('evolution_statuts_choisir_haut_niveau', [
                'slug' => $form->get('groupes')->getData()->getSlug()
            ]);
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 1,
            'indentationGroupes' => false,
            'activerToutSelectionner' => false,
            'casBoutonValider' => 0,
            'titrePage' => 'Évolution chronologique des résultats d’un ensemble d’étudiants appartenant à un statut',
            'typeForm1' => 'groupes',
            'sousTitreForm1' => 'Sélectionner le groupe d\'étudiants ayant un statut particulier pour lequel vous souhaitez voir des statistiques',
            'messageWarningForm1' => 'La couleur de l\'effectif d\'un groupe indique la facilité de lecture du graphique qui sera généré pour ce groupe.',
            'conditionAffichageForm1' => true,
            'colorationEffectif' => true,
            'affichageEffectifParStatut' => false
        ]);
    }

    /**
     * @Route("/evolution/statut/{slug}/choisir-groupe", name="evolution_statuts_choisir_haut_niveau")
     */
    public function evolutionStatutsChoisirGroupeHautNiveau(Request $request, Statut $statut, EtudiantRepository $repoEtudiant, GroupeEtudiantRepository $repoGroupe): Response
    {
        $session = $request->getSession();
        $choices = $repoGroupe->findHighestEvaluableWith1EvalOrMore();
        $form = $this->createFormBuilder()
            ->add('groupes', EntityType::class, [
                'class' => GroupeEtudiant::Class,
                'constraints' => [new NotBlank()],
                'choice_label' => false,
                'label' => false,
                'expanded' => true,
                'multiple' => false,
                'choices' => $choices // On choisira parmis les groupes de plus haut niveau évaluables qui ont au moins 1 évaluation que les concernent
            ])
            ->getForm();

        $effectifsParStatut = [];
        foreach ($choices as $groupeAChoisir) {
            array_push($effectifsParStatut, count($repoEtudiant->findAllByOneStatutAndOneGroupe($statut, $groupeAChoisir)));
        }

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $session->set('groupeHautNiveau', $form->get('groupes')->getData());
            return $this->redirectToRoute('evolution_statut_choisir_sous_groupes', [
                'slug' => $statut->getSlug()
            ]);
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 1,
            'colorationEffectif' => true,
            'indentationGroupes' => false,
            'casBoutonValider' => 0,
            'activerToutSelectionner' => false,
            'titrePage' => 'Évolution chronologique des résultats d’un ensemble d’étudiants appartenant à un statut',
            'typeForm1' => 'groupes',
            'sousTitreForm1' => 'Sélectionner le groupe pour lequel vous souhaitez voir des statistiques pour le statut concerné précédemment sélectionné',
            'conditionAffichageForm1' => true,
            'affichageEffectifParStatut' => true,
            'effectifsParStatut' => $effectifsParStatut,
            'messageWarningForm1' => 'La couleur de l\'effectif d\'un groupe indique la facilité de lecture du graphique qui sera généré pour ce groupe.'
        ]);
    }

    /**
     * @Route("/evolution/statut/{slug}/choisir-sous-groupes", name="evolution_statut_choisir_sous_groupes")
     */
    public function evolutionStatutChoisirSousGroupes(Request $request, Statut $statut, GroupeEtudiantRepository $repoGroupe, EtudiantRepository $repoEtudiant): Response
    {
        $session = $request->getSession();
        $groupe = $session->get('groupeHautNiveau');
        $sousGroupes = $repoGroupe->findAllOrderedFromNode($groupe);
        $form = $this->createFormBuilder()
            ->add('groupes', EntityType::class, [
                'constraints' => [
                    new NotBlank()
                ],
                'class' => GroupeEtudiant::Class,
                'choice_label' => false,
                'label' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $sousGroupes
            ])
            ->getForm();
        $form->handleRequest($request);
        $effectifsParStatut = array();
        foreach ($sousGroupes as $groupeAChoisir) {
            array_push($effectifsParStatut, count($repoEtudiant->findAllByOneStatutAndOneGroupe($statut, $groupeAChoisir)));
        }
        if ($form->isSubmitted() && $form->isValid()) {
            if (count($form->get('groupes')->getData()) > 0) {
                $sousGroupes = $form->get('groupes')->getData();
                $request->getSession()->set('sousGroupes', $sousGroupes);
                return $this->redirectToRoute('evolution_statut_choisir_evaluations', [
                    'slug' => $statut->getSlug()
                ]);
            }
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 1,
            'colorationEffectif' => true,
            'indentationGroupes' => true,
            'casBoutonValider' => 3,
            'activerToutSelectionner' => true,
            'titrePage' => 'Évolution chronologique des résultats d’un ensemble d’étudiants appartenant à un statut',
            'typeForm1' => 'groupes',
            'sousTitreForm1' => 'Sélectionner les sous-groupes de ' . $groupe->getNom() . ' comportant des étudiants du statut \'' . $statut->getNom() . '\' et pour lesquels vous souhaitez voir des statistiques',
            'conditionAffichageForm1' => true,
            'affichageEffectifParStatut' => true,
            'effectifsParStatut' => $effectifsParStatut,
            'messageWarningForm1' => 'La couleur de l\'effectif d\'un groupe indique la facilité de lecture du graphique qui sera généré pour ce groupe.'
        ]);
    }

    /**
     * @Route("/evolution/statut/{slug}/choisir-evaluations", name="evolution_statut_choisir_evaluations")
     */
    public function evolutionStatutChoisirEvals(Request $request, EvaluationRepository $repoEval, GroupeEtudiantRepository $repoGroupe, StatisticsManager $statsManager, Statut $statut): Response
    {
        $session = $request->getSession();
        $form = $this->createFormBuilder()
            ->add('evaluations', EntityType::class, [
                'class' => Evaluation::Class,
                'choice_label' => false,
                'label' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $repoGroupe->find($session->get('groupeHautNiveau')->getId())->getEvaluations()
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $evaluations = $form->get('evaluations')->getData();
            $tabEvaluations = array();
            foreach ($evaluations as $evaluation) {
                array_push($tabEvaluations, $repoEval->find($evaluation->getId()));
            }
            $lesGroupes = []; // On regroupe le groupe principal et les sous groupes pour faciliter la requete
            foreach ($request->getSession()->get('sousGroupes') as $sousGroupe) {
                array_push($lesGroupes, $repoGroupe->find($sousGroupe->getId()));
            }
            //Pour avoir les évaluations ordonnées chronologiquement
            usort($tabEvaluations, function ($a, $b) {
                if ($a->getdate() == $b->getdate()) {
                    return 0;
                }
                return ($a->getdate() < $b->getdate()) ? -1 : 1;
            });
            return $this->render('statistiques/affichage_stats_evolution.html.twig', [
                'evaluations' => $tabEvaluations,
                'groupes' => $lesGroupes,
                'titre' => 'Évolution chronologique des résultats d’un ensemble d’étudiants',
                'stats' => $statsManager->calculerStatsEvolution('statut', $lesGroupes, $tabEvaluations, $statut)
            ]);
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 1,
            'titrePage' => 'Évolution chronologique des résultats d’un ensemble d’étudiants',
            'activerToutSelectionner' => true,
            'typeForm1' => 'evaluations',
            'sousTitreForm1' => 'Sélectionner les évaluations pour lesquelles vous souhaitez voir des statistiques pour les groupes précédemment sélectionnés',
            'conditionAffichageForm1' => true,
            'casBoutonValider' => 4
        ]);
    }

    ///////////////////////////////
    //FIN STATS EVOLUTION STATUTS//
    ///////////////////////////////

    /////////////////////////
    ////STATS COMPARAISON////
    /////////////////////////

    /**
     * @Route("/comparaison/{typeGraphique}/choisir-evaluation-reference", name="comparaison_choisir_evaluation_reference", methods={"GET", "POST"})
     */
    public function comparaisonChoisirEvaluationReference($typeGraphique, EvaluationRepository $repoEval, Request $request) : Response
    {
        //On met en sesssion le type de graphique choisi par l'utilisateur pour afficher l'onglet correspondant lors de l'affichage des stats
        $request->getSession()->set('typeGraphique', $typeGraphique);
        $form = $this->createFormBuilder()
            ->add('evaluations', EntityType::class, [
                'constraints' => [new NotNull],
                'class' => Evaluation::Class,
                'choice_label' => false,
                'label' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => false,
                'choices' => $repoEval->findAll()
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return $this->redirectToRoute('statistiques_comparaison_choisir_autres_evals', [
                'slug' => $form->get('evaluations')->getData()->getSlug(),
            ]);
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 1,
            'titrePage' => 'Comparaison des résultats d’une évaluation spécifique à un ensemble d’évaluations',
            'activerToutSelectionner' => false,
            'colorationEffectif' => false,
            'casBoutonValider' => 0,
            'typeForm1' => 'evaluations',
            'conditionAffichageForm1' => true,
            'sousTitreForm1' => 'Choisir l\'évaluation de référence qui sera comparée à un ensemble d’évaluations',
        ]);
    }

    /**
     * @Route("/comparaison/{slug}/choisir-autres-evaluations", name="statistiques_comparaison_choisir_autres_evals", methods={"GET","POST"})
     */
    public function comparaisonChoisirAutresEvaluations(Request $request, Evaluation $evaluation, EvaluationRepository $repoEval): Response
    {
        $evaluationsDispos = $repoEval->findAllOverAGroupExceptCurrentOne($evaluation->getGroupe()->getId(), $evaluation->getId());
        $form = $this->createFormBuilder()
            ->add('evaluations', EntityType::class, [
                'constraints' => [new NotNull],
                'class' => Evaluation::Class,
                'choice_label' => false,
                'label' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $evaluationsDispos
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (count($form->get('evaluations')->getData()) > 0) {
                $evaluationsChoisies = $form->get('evaluations')->getData();
                $session = $request->getSession();
                $session->set('evaluationsChoisies', $evaluationsChoisies);
                return $this->redirectToRoute('comparaison_choisir_groupes_et_statuts', [
                    'slug' => $evaluation->getSlug(),
                ]);
            }
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 1,
            'titrePage' => 'Comparaison des résultats d’une évaluation spécifique à un ensemble d’évaluations',
            'activerToutSelectionner' => true,
            'colorationEffectif' => false,
            'casBoutonValider' => 4,
            'typeForm1' => 'evaluations',
            'sousTitreForm1' => 'Sélectionner l\'ensemble des évaluations dont la moyenne globale sera comparée à la moyenne de l\'évaluation de référence ' . $evaluation->getNom(),
            'conditionAffichageForm1' => !count($evaluationsDispos) == 0,
            'messageAlternatifForm1' => '<p> Aucune évaluation n\'est comparable avec l\'évaluation ' . $evaluation->getNom() . '. Vous pouvez créer des évaluations <a href="'. $this->generateUrl('evaluation_choose_group', ['typeEval' => 'simple']) . '">ici</a> où bien sélectionner une autre évaluation de référence à <a href="#" onclick="window.history.back()">l\'étape précédente</a>.</p>'
        ]);
    }

    /**
     * @Route("/comparaison/{slug}/choisir-groupes-et-statuts", name="comparaison_choisir_groupes_et_statuts", methods={"GET","POST"})
     */
    public function comparaisonChoisirParametreEtAfficherStats(Request $request, StatisticsManager $statsManager, Evaluation $evaluation, StatutRepository $repoStatut, GroupeEtudiantRepository $repoGroupe): Response
    {
        $session = $request->getSession();
        $evaluationsChoisies = $session->get('evaluationsChoisies');
        $groupeConcerne = $evaluation->getGroupe();
        //On récupère la liste de tous les enfants (directs et indirects) du groupe concerné pour choisir ceux sur lesquels on veut des statistiques
        $choixGroupe = $repoGroupe->findAllOrderedFromNode($groupeConcerne);
        $choixStatuts = $repoStatut->findByEvaluation($evaluation->getId()); // On choisira parmis les statuts qui possède au moins 1 étudiant ayant participé à l'évaluation
        $form = $this->createFormBuilder()
            ->add('groupes', EntityType::class, [
                'class' => GroupeEtudiant::Class,
                'choice_label' => false,
                'label' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $choixGroupe // On choisira parmis le groupe concerné et ses enfants
            ])
            ->add('statuts', EntityType::class, [
                'class' => Statut::Class,
                'choice_label' => false,
                'label' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $choixStatuts
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $groupes = $form->get('groupes')->getData();
            $statuts = $form->get('statuts')->getData();
            return $this->render('statistiques/affichage_stats_comparaison.html.twig', [
                'evaluations' => $evaluationsChoisies,
                'evaluationConcernee' => $evaluation,
                'groupes' => $groupes,
                "parties" => $statsManager->calculerStatsComparaison($evaluation, $groupes, $statuts, $evaluationsChoisies),
                'titre' => "Comparer " . $evaluation->getNom() . " à " . (count($evaluationsChoisies)) . ' évaluation(s)',
                'plusieursEvals' => true,
            ]);
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 2,
            'activerToutSelectionner' => true,
            'titrePage' => "Comparaison des résultats d’une évaluation spécifique à un ensemble d’évaluations",
            'colorationEffectif' => false,
            'casBoutonValider' => 1,
            'typeForm1' => 'groupes',
            'sousTitreForm1' => 'Sélectionner les groupes pour lesquels vous souhaitez consulter les statistiques',
            'conditionAffichageForm1' => true,
            'indentationGroupes' => true,
            'typeForm2' => 'statuts',
            'sousTitreForm2' => 'Sélectionner les groupes d\'étudiants ayant un statut particulier pour lesquels vous souhaitez consulter les statistiques',
            'conditionAffichageForm2' => !empty($choixStatuts),
            'messageAlternatifForm2' => 'Il est possible d\'obtenir des statistiques sur des groupes d\'étudiantsayant un statut particulier (boursiers, redoublants, ...). Vous pouvez créer de tels groupes <a href="' . $this->generateUrl('statut_new') . '">ici</a>.'
        ]);
    }

    /////////////////////////
    //FIN STATS COMPARAISON//
    /////////////////////////

    ///////////////////////
    ///////ENVOI MAIL//////
    ///////////////////////

    /**
     * @Route("/previsualisation-mail/{slug}", name="previsualisation_mail", methods={"GET", "POST"})
     */
    public function envoiMail(Evaluation $evaluation, Request $request, PointsRepository $pointsRepository, Swift_Mailer $mailer, Filesystem $filesystem): Response
    {
        $nbEtudiants = count($evaluation->getGroupe()->getEtudiants());
        $nomGroupe = $evaluation->getGroupe()->getNom();
        $this->denyAccessUnlessGranted('EVALUATION_PREVISUALISATION_MAIL', $evaluation);
        $form = $this->createFormBuilder()
            ->add('fichierPDF', FileType::class, [
                'required' => false,
                'constraints' => [new File([
                    'maxSize' => '8Mi',
                    'mimeTypes' => 'application/pdf',
                    'mimeTypesMessage' => 'Le fichier ajouté n\'est pas un fichier pdf',
                    'uploadFormSizeErrorMessage' => 'Le fichier ajouté est trop volumineux'
                ])],
                'attr' => [
                    'placeholder' => 'Aucun fichier choisi',
                    'accept' => '.pdf'
                ]
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $fichier = $form->get('fichierPDF')->getData();
            $filesystem->remove(['symlink', "pdf_temp", 'activity.log']); //Pour vider le stockage temporaire du précédent pdf envoyé
            //Pour ne traiter le fichier optionnel que si il est déposé
            if ($fichier) {
                $originalFilename = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
                //Rajout de l'extension au nom de fichier
                $newFilename = $originalFilename . '.' . $fichier->guessExtension();
                //Déplacement du fichier
                $fichier->move(
                    "pdf_temp", //Déplacé dans le dossier pdf_temp dans public
                    $newFilename
                );
            }
            $session = $request->getSession();
            // Récupération des stats mises en session
            $stats = $session->get('stats');
            $notesEtudiants = $pointsRepository->findNotesAndEtudiantByEvaluation($evaluation);
            $tabRang = $pointsRepository->findAllNotesByGroupe($evaluation->getId(), $evaluation->getGroupe()->getId());
            $copieTabRang = array();
            foreach ($tabRang as $element) {
                $copieTabRang[] = $element["valeur"];
            }
            $effectif = sizeof($copieTabRang);
            $mailAdmin = $_ENV['MAIL_ADMINISTRATEUR'];
            for ($i = 0; $i < count($notesEtudiants); $i++) {
                $noteEtudiant = $notesEtudiants[$i]->getValeur();
                $position = array_search($noteEtudiant, $copieTabRang) + 1;
                $message = (new Swift_Message('Noteo - ' . $evaluation->getNom()))
                    ->setFrom($_ENV['UTILISATEUR_SMTP'])
                    ->setTo($notesEtudiants[$i]->getEtudiant()->getMail())
                    ->setBody(
                        $this->renderView('evaluation/mailEnvoye.html.twig', [
                            'etudiantsEtNotes' => $notesEtudiants[$i],
                            'stats' => $stats,
                            'position' => $position,
                            'effectif' => $effectif,
                            'mailAdmin' => $mailAdmin
                        ]), 'text/html');
                if ($fichier) { //Si le pdf est ajouté on le joint au mail
                    $message->attach(Swift_Attachment::fromPath('pdf_temp/' . $newFilename));
                }
                $mailer->send($message);
            }
            $this->addFlash(
                'info',
                'L\'envoi des mails a été effectué avec succès.'
            );
            return $this->render('statistiques/affichage_stats_classiques.html.twig', [
                'titrePage' => 'Consulter les statistiques pour ' . $evaluation->getNom(),
                'plusieursEvals' => false,
                'parties' => $stats,
                'evaluation' => $evaluation
            ]);

        }
        return $this->render('evaluation/previsualisationMail.html.twig', [
            'evaluation' => $evaluation,
            'nbEtudiants' => $nbEtudiants,
            'nomGroupe' => $nomGroupe,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/exemple-mail/{id}", name="exemple_mail", methods={"GET"})
     */
    public function exempleMail(Request $request, Evaluation $evaluation, PointsRepository $pointsRepository): Response
    {
        $this->denyAccessUnlessGranted('EVALUATION_EXEMPLE_MAIL', $evaluation);
        // Récupération de la session
        $session = $request->getSession();
        // Récupération des stats mises en session
        $stats = $session->get('stats');
        $notesEtudiants = $pointsRepository->findNotesAndEtudiantByEvaluation($evaluation);
        $tabRang = $pointsRepository->findAllNotesByGroupe($evaluation->getId(), $evaluation->getGroupe()->getId());
        $copieTabRang = array();
        foreach ($tabRang as $element) {
            $copieTabRang[] = $element["valeur"];
        }
        $effectif = sizeof($copieTabRang);
        $noteEtudiant = $notesEtudiants[0]->getValeur();
        $position = array_search($noteEtudiant, $copieTabRang) + 1;
        $mailAdmin = $_ENV['MAIL_ADMINISTRATEUR'];
        return $this->render('evaluation/mailEnvoye.html.twig', [
            'etudiantsEtNotes' => $notesEtudiants[0],
            'stats' => $stats,
            'position' => $position,
            'effectif' => $effectif,
            'mailAdmin' => $mailAdmin
        ]);
    }

    ///////////////////////
    /////FIN ENVOI MAIL////
    ///////////////////////
}
