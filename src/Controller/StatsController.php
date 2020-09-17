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
        $nombreEtudiantsConcerneParUneEvalOuPlus = count($repoEtudiant->findAllConcernedByAtLeastOneEvaluation());
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

    //<editor-fold desc="Statistiques évaluation simple">
    ///////////////////////
    ///STATS EVAL SIMPLE///
    ///////////////////////

    /**
     * @Route("/eval-simple/{typeGraphique}/choisir-evaluation", name="eval_simple_choisir_evaluation", methods={"GET", "POST"})
     */
    public function evalSimpleChoisirEvaluation($typeGraphique, EvaluationRepository $repoEval, Request $request) : Response
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
            $statistiquesCalculees = $statsManager->calculerStats('classique', $evaluation, $groupesChoisis, $statutsChoisis, $evaluation->getParties());
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
    //</editor-fold>

    //<editor-fold desc="Statistiques évaluation parties">
    ///////////////////////
    ///STATS EVAL PARTIE///
    ///////////////////////

    /**
     * @Route("/eval-parties/{typeGraphique}/choisir-evaluation", name="eval_parties_choisir_evaluation", methods={"GET", "POST"})
     */
    public function evalPartiesChoisirEvaluation($typeGraphique, EvaluationRepository $repoEval, Request $request) : Response
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
                'choices' => $repoEval->findAllWithSeveralParts()
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return $this->redirectToRoute('eval_parties_choix_parametres_et_afficher_stats', [
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
     * @Route("/eval-parties/{slug}/choisir-groupes-et-statuts", name="eval_parties_choix_parametres_et_afficher_stats", methods={"GET","POST"})
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
            $statistiquesCalculees = $statsManager->calculerStats('classique-parties', $evaluation, $groupesChoisis, $statutsChoisis, $partiesChoisies);
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
    //</editor-fold>

    //<editor-fold desc="Statistiques plusieurs évals groupes">
    ///////////////////////////////
    //STATS PLUSIEURS EVAL GROUPE//
    ///////////////////////////////


    ///////////////////////////////////
    //FIN STATS PLUSIEURS EVAL GROUPE//
    ///////////////////////////////////
    //</editor-fold>

    //<editor-fold desc="Statistiques plusieurs évals statuts">
    ////////////////////////////////
    //STATS PLUSIEURS EVAL STATUTS//
    ////////////////////////////////

    ////////////////////////////////////
    //FIN STATS PLUSIEURS EVAL STATUTS//
    ////////////////////////////////////
    //</editor-fold>

    //<editor-fold desc="Statistiques fiche étudiant">
    ////////////////////////
    //STATS FICHE ETUDIANT//
    ////////////////////////

    /////////////////////////////
    //FIN STATS FICHE ETUDIANT //
    /////////////////////////////
    //</editor-fold>

    //<editor-fold desc="Statistiques évolution groupe">
    ///////////////////////////////
    //STATS EVOLUTION GROUPE//
    ///////////////////////////////

    //////////////////////////////
    //FIN STATS EVOLUTION GROUPE//
    //////////////////////////////
    //</editor-fold>

    //<editor-fold desc="Statistiques évolution statuts">
    ///////////////////////////////
    ////STATS EVOLUTION STATUTS////
    ///////////////////////////////

    ///////////////////////////////
    //FIN STATS EVOLUTION STATUTS//
    ///////////////////////////////
    //</editor-fold>

    //<editor-fold desc="Statistiques comparaison évaluations">
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
    public function comparaisonChoisirParametreEtAfficherStats(Request $request, StatisticsManager $statsManager, Evaluation $evaluation, StatutRepository $repoStatut, GroupeEtudiantRepository $repoGroupe, PointsRepository $repoPoints): Response
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
                "parties" => $statsManager->calculerStats('comparaison', $evaluation, $groupes, $statuts, null, $evaluationsChoisies),
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
    //</editor-fold>

    //<editor-fold desc="Envoi du mail aux étudiants">
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
    //</editor-fold>



}
