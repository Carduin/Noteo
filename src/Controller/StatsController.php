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
     * @Route("/choix-statistiques", name="choix_statistiques", methods={"GET"})
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
        return $this->render('statistiques/choix_statistiques.html.twig', [
            "statistiques" => $statsDispo
        ]);
    }

    /**
     * @Route("/{typeStat}/{typeGraph}/choix-evaluation", name="statistiques_choix_evaluation", methods={"GET", "POST"})
     */
    public function choixEvaluation($typeStat, EvaluationRepository $repoEval, Request $request, $typeGraph): Response
    {
        //On met en sesssion le type de graphique choisi par l'utilisateur pour afficher l'onglet correspondant lors de l'affichage des stats
        $request->getSession()->set('typeGraphique', $typeGraph);
        $evaluations = array();
        $titrePage = "";
        $sousTitrePage = "";
        switch ($typeStat) {
            case 'classique':
                $evaluations = $repoEval->findAllWithOnePart();
                $titrePage = "Analyse d’une évaluation simple";
                $sousTitrePage = "Choisir l'évaluation pour laquelle vous désirez consulter les statistiques";
                break;
            case 'classique-avec-parties' :
                $evaluations = $repoEval->findAllWithSeveralParts();
                $titrePage = "Analyse d’une évaluation avec parties";
                $sousTitrePage = "Choisir l'évaluation pour laquelle vous désirez consulter les statistiques";
                break;
            case 'comparaison' :
                $evaluations = $repoEval->findAll();
                $titrePage = "Comparaison des résultats d’une évaluation spécifique à un ensemble d’évaluations";
                $sousTitrePage = "Choisir l'évaluation de référence qui sera comparée à un ensemble d’évaluations";
                break;
        }
        $form = $this->createFormBuilder()
            ->add('evaluations', EntityType::class, [
                'constraints' => [new NotNull],
                'class' => Evaluation::Class,
                'choice_label' => false,
                'label' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => false,
                'choices' => $evaluations
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            switch ($typeStat) {
                case 'classique':
                case 'classique-avec-parties' :
                    return $this->redirectToRoute('statistiques_classiques_choisir_groupes_parties_statuts', [
                        'slug' => $form->get('evaluations')->getData()->getSlug(),
                        'typeStat' => $typeStat
                    ]);
                case 'comparaison' :
                    return $this->redirectToRoute('statistiques_comparaison_choisir_autres_evals', [
                        'slug' => $form->get('evaluations')->getData()->getSlug(),
                    ]);
            }
        }
        return $this->render('statistiques/choix_evaluation.html.twig', [
            'form' => $form->createView(),
            'titrePage' => $titrePage,
            'sousTitrePage' => $sousTitrePage
        ]);
    }

    /**
     * @Route("/comparaison/{slug}/choisir-autres-evals", name="statistiques_comparaison_choisir_autres_evals", methods={"GET","POST"})
     */
    public function choisirEvalsComparaison(Request $request, Evaluation $evaluation, EvaluationRepository $repoEval): Response
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
            $evaluationsChoisies = array();
            if (count($form->get('evaluations')->getData()) > 0) {
                $evaluationsChoisies = $form->get('evaluations')->getData();
            }
            $session = $request->getSession();
            $session->set('evaluationsChoisies', $evaluationsChoisies);
            return $this->redirectToRoute('statistiques_comparaison_choisir_groupes_statuts', [
                'slug' => $evaluation->getSlug(),
            ]);
        }
        return $this->render('statistiques/choix_plusieurs_evals_comparaison.html.twig', [
            'form' => $form->createView(),
            'titrePage' => "Comparaison des résultats d’une évaluation spécifique à un ensemble d’évaluations",
            'evaluation' => $evaluation,
            'dispoEvals' => !count($evaluationsDispos) == 0
        ]);
    }

    /**
     * @Route("/comparaison/{slug}/choisir-groupes-et-statuts", name="statistiques_comparaison_choisir_groupes_statuts", methods={"GET","POST"})
     */
    public function choisirGroupesEtStatutscomparaison(Request $request, StatisticsManager $statsManager, Evaluation $evaluation, StatutRepository $repoStatut, GroupeEtudiantRepository $repoGroupe, PointsRepository $repoPoints): Response
    {
        $session = $request->getSession();
        $evaluationsChoisies = $session->get('evaluationsChoisies');
        $groupeConcerne = $evaluation->getGroupe();
        //On récupère la liste de tous les enfants (directs et indirects) du groupe concerné pour choisir ceux sur lesquels on veut des statistiques
        $choixGroupe = $repoGroupe->findAllOrderedFromNode($groupeConcerne);
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
                'choices' => $repoStatut->findByEvaluation($evaluation->getId()) // On choisira parmis les statuts qui possède au moins 1 étudiant ayant participé à l'évaluation
                // 'choices' => []
            ])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $groupes = $form->get('groupes')->getData();
            $statuts = $form->get('statuts')->getData();
            /// GENERATION DES STATISTIQUES
            $tabStatsComparaison = array();
            foreach ($groupes as $groupe) {
                // déterminer la moyenne du groupe courant à l'évaluation de référence
                $pointsEvaluationGroupe = $repoPoints->findAllNotesByGroupe($evaluation->getId(), $groupe->getId());
                $moyenneEvaluationGroupe = array();
                foreach ($pointsEvaluationGroupe as $note) {
                    $moyenneEvaluationGroupe[] = $note["valeur"];
                }
                $moyenneEvaluationCouranteGroupe = $statsManager->moyenne($moyenneEvaluationGroupe);
                //déterminer la moyenne des moyennes aux évaluations
                $moyennesGroupeTmp = array();
                foreach ($evaluationsChoisies as $evaluationCourante) { // pour chaque évaluation, on détermine sa moyenne pour le groupe courant
                    //determiner la moyenne de l'évaluation courante
                    $tabPoints = $repoPoints->findAllNotesByGroupe($evaluationCourante->getId(), $groupe->getId()); // on récupère les notes
                    //on crée un tableau temporaire ou on stoque séparement chaque note
                    $copieTab = array();
                    foreach ($tabPoints as $note) {
                        $copieTab[] = $note["valeur"];
                    }
                    $moyenneEvaluationCourante = $statsManager->moyenne($copieTab); // on determine la moyenne du controle courant
                    array_push($moyennesGroupeTmp, $moyenneEvaluationCourante);
                }
                //on détermine la moyenne des moyennes
                $moyenneDesMoyennesEvaluations = $statsManager->moyenne($moyennesGroupeTmp);
                $tabStatsComparaison[] = [
                    "nom" => $groupe->getNom(),
                    "moyenneControleCourant" => $moyenneEvaluationCouranteGroupe,
                    "moyenneAutresControles" => $moyenneDesMoyennesEvaluations,
                ];
            }
            ///on traite les statistiques pour tous les statuts
            foreach ($statuts as $statut) {
                /// déterminer la moyenne du groupe courant à l'évaluation
                $pointsEvaluationStatut = $repoPoints->findAllNotesByStatut($evaluation->getId(), $statut->getId());
                $moyenneEvaluationCouranteStatut = array();
                foreach ($pointsEvaluationStatut as $note) {
                    $moyenneEvaluationCouranteStatut[] = $note["valeur"];
                }
                $moyenneEvaluationCouranteStatut = $statsManager->moyenne($moyenneEvaluationCouranteStatut);
                /// déterminer la moyenne des moyennes aux évaluations
                $moyennesTmp = array();

                foreach ($evaluationsChoisies as $evaluationCourante) { // pour chaque évaluation, on détermine sa moyenne pour le groupe courant
                    //determiner la moyenne de l'évaluation courante
                    $tabPoints = $repoPoints->findAllNotesByStatut($evaluationCourante->getId(), $statut->getId()); // on récupère les notes
                    //on crée un tableau temporaire ou on stoque séparement chaque note
                    $copieTab = array();
                    foreach ($tabPoints as $note) {
                        $copieTab[] = $note["valeur"];
                    }
                    $moyenneEvaluationCourante = $statsManager->moyenne($copieTab); // on determine la moyenne du controle courant
                    array_push($moyennesTmp, $moyenneEvaluationCourante);
                }
                //on détermine la moyenne des moyennes
                $moyenneDesMoyennesEvaluations = $statsManager->moyenne($moyennesTmp);
                $tabStatsComparaison[] = [
                    "nom" => $statut->getNom(),
                    "moyenneControleCourant" => $moyenneEvaluationCouranteStatut,
                    "moyenneAutresControles" => $moyenneDesMoyennesEvaluations,
                ];
            }
            $formatAdapteALaVue = [[
                "nom" => "Comparaison de " . $evaluation->getNom() . " à " . (count($evaluationsChoisies)) . ' évaluation(s)',
                "stats" => $tabStatsComparaison
            ]
            ];
            return $this->render('statistiques/statsComparaison.html.twig', [
                'evaluations' => $evaluationsChoisies,
                'evaluationConcernee' => $evaluation,
                'groupes' => $groupes,
                "parties" => $formatAdapteALaVue,
                'titre' => "Comparer " . $evaluation->getNom() . " à " . (count($evaluationsChoisies)) . ' évaluation(s)',
                'plusieursEvals' => true,
            ]);
        }
        return $this->render('statistiques/choix_groupes_et_parties.html.twig', [
            'form' => $form->createView(),
            'evaluation' => $evaluation,
            'pasDeChoixParties' => true,
            'evaluations' => $evaluationsChoisies,
            'titrePage' => " Comparaison des résultats d’une évaluation spécifique à un ensemble d’évaluations"
        ]);
    }

    /**
     * @Route("/{typeStat}/{slug}/choisir-groupes-et-statuts", name="statistiques_classiques_choisir_groupes_parties_statuts", methods={"GET","POST"})
     */
    public function choisirGroupesPartiesEtStatuts(Request $request, StatisticsManager $statsManager, $typeStat, Evaluation $evaluation, StatutRepository $repoStatut, GroupeEtudiantRepository $repoGroupe, PointsRepository $repoPoints): Response
    {
        $titrePage = "";
        switch ($typeStat) {
            case 'classique':
                $titrePage = "Analyse d’une évaluation simple";
                break;
            case 'classique-avec-parties' :
                $titrePage = "Analyse d’une évaluation avec parties";
                break;
        }
        $session = $request->getSession();
        $groupeConcerne = $evaluation->getGroupe();
        //On récupère la liste de tous les enfants (directs et indirects) du groupe concerné pour choisir ceux sur lesquels on veut des statistiques
        $choixGroupe = $repoGroupe->findAllOrderedFromNode($groupeConcerne);
        $formBuilder = $this->createFormBuilder();
        if (count($evaluation->getParties()) > 1) {
            $formBuilder
                ->add('parties', EntityType::class, [
                    'class' => Partie::Class,
                    'choice_label' => false,
                    'label' => false,
                    'mapped' => false,
                    'expanded' => true,
                    'multiple' => true,
                    'choices' => $evaluation->getParties() // On choisira parmis le groupe concerné et ses enfants
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
                'choices' => $choixGroupe // On choisira parmis le groupe concerné et ses enfants
            ])
            ->add('statuts', EntityType::class, [
                'class' => Statut::Class,
                'choice_label' => false,
                'label' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $repoStatut->findByEvaluation($evaluation->getId()) // On choisira parmis les statuts qui possède au moins 1 étudiant ayant participé à l'évaluation
            ]);
        $form = $formBuilder->getForm()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $groupesChoisis = $form->get("groupes")->getData();
            $statutsChoisis = $form->get("statuts")->getData();
            if (count($groupesChoisis) > 0 || count($statutsChoisis) > 0) {
                if (count($evaluation->getParties()) > 1) {
                    $partiesChoisies = $form->get("parties")->getData();
                } else {
                    $partiesChoisies = $evaluation->getParties();
                }
                $toutesLesStats = [];
                //Calcul des stats pour toutes les parties
                foreach ($partiesChoisies as $partie) {
                    $statsDuGroupePourLaPartie = [];
                    foreach ($groupesChoisis as $groupe) {
                        $notesGroupe = $repoPoints->findByGroupeAndPartie($evaluation->getId(), $groupe->getId(), $partie->getId());
                        //On fait une copie du résultat de la requête pour simplifier le format de renvoi utilisé par doctrine
                        $copieTabPoints = array();
                        foreach ($notesGroupe as $element) {
                            $copieTabPoints[] = $element["valeur"];
                        }
                        $statsDuGroupePourLaPartie[] = [
                            "nom" => $groupe->getNom(),
                            "repartition" => $statsManager->repartition($copieTabPoints),
                            "listeNotes" => $copieTabPoints,
                            "moyenne" => $statsManager->moyenne($copieTabPoints),
                            "ecartType" => $statsManager->ecartType($copieTabPoints),
                            "minimum" => $statsManager->minimum($copieTabPoints),
                            "maximum" => $statsManager->maximum($copieTabPoints),
                            "mediane" => $statsManager->mediane($copieTabPoints),
                        ];
                    }
                    $statsDuStatutPourLaPartie = [];
                    foreach ($statutsChoisis as $statut) {
                        $notesStatut = $repoPoints->findByStatutAndPartie($evaluation->getId(), $statut->getId(), $partie->getId());
                        //On fait une copie du résultat de la requête pour simplifier le format de renvoi utilisé par doctrine
                        $copieTabPoints = array();
                        foreach ($notesStatut as $element) {
                            $copieTabPoints[] = $element["valeur"];
                        }
                        $statsDuStatutPourLaPartie[] = [
                            "nom" => $statut->getNom(),
                            "repartition" => $statsManager->repartition($copieTabPoints),
                            "listeNotes" => $copieTabPoints,
                            "moyenne" => $statsManager->moyenne($copieTabPoints),
                            "ecartType" => $statsManager->ecartType($copieTabPoints),
                            "minimum" => $statsManager->minimum($copieTabPoints),
                            "maximum" => $statsManager->maximum($copieTabPoints),
                            "mediane" => $statsManager->mediane($copieTabPoints),
                        ];
                    }
                    //Ajout des stats de la partie (groupe + statut) dans le tableau général
                    $toutesLesStats[] = [
                        "nom" => $partie->getIntitule(),
                        "bareme" => $partie->getBareme(),
                        "stats" => array_merge($statsDuGroupePourLaPartie, $statsDuStatutPourLaPartie)
                    ];
                }
                //Mise en session des stats pour le mail et la page de visualisation
                $session->set('stats', $toutesLesStats);
                return $this->render('statistiques/stats.html.twig', [
                    'titrePage' => 'Statistiques pour ' . $evaluation->getNom(),
                    'plusieursEvals' => false,
                    'evaluation' => $evaluation,
                    'parties' => $toutesLesStats
                ]);
            }
        }
        return $this->render('statistiques/choix_groupes_et_parties.html.twig', [
            'form' => $form->createView(),
            'evaluation' => $evaluation,
            'pasDeChoixParties' => false,
            'titrePage' => $titrePage
        ]);
    }

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
            return $this->render('statistiques/stats.html.twig', [
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

    /**
     * @Route("/plusieurs-eval/groupes/{typeGraph}/choisir-groupe", name="stats_choisir_groupe_haut_niveau_evaluable")
     */
    public function choisirGroupeStatsPlusieursEvals(Request $request, GroupeEtudiantRepository $repoGroupe, EtudiantRepository $repoEtudiant, $typeGraph): Response
    {
        $session = $request->getSession();
        //On met en sesssion le type de graphique choisi par l'utilisateur pour afficher l'onglet correspondant lors de l'affichage des stats
        $request->getSession()->set('typeGraphique', $typeGraph);
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
        $effectifsParStatut = array();
        if ($typeGraph == "evolutionStatut") {
            $statutChoisi = $session->get('statut');
            foreach ($choices as $groupeAChoisir) {
                array_push($effectifsParStatut, count($repoEtudiant->findAllByOneStatutAndOneGroupe($statutChoisi, $groupeAChoisir)));
            }
        }

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return $this->redirectToRoute('statistiques_choisir_sous_groupes', ['slug' => $form->get('groupes')->getData()->getSlug()]);
        }
        return $this->render('statistiques/choix_groupes_plusieurs_evals.html.twig', [
            'form' => $form->createView(),
            'pasDIndentation' => true,
            'effectifsParStatut' => $effectifsParStatut,
            'typeGraph' => $typeGraph
        ]);
    }

    /**
     * @Route("/plusieurs-eval/groupes/{slug}/choisir-sous-groupes", name="statistiques_choisir_sous_groupes")
     */
    public function choisirSousGroupesStatsPlusieursEvals(Request $request, GroupeEtudiant $groupe, GroupeEtudiantRepository $repoGroupe, EtudiantRepository $repoEtudiant): Response
    {
        $session = $request->getSession();
        $typeGraph = $request->getSession()->get('typeGraphique');   // Récupération du type de stat dans la session
        $statut = $request->getSession()->get('statut');
        $groupesAChoisir = array();
        $sousGroupes = $repoGroupe->findAllOrderedFromNode($groupe);
        foreach ($sousGroupes as $sousGroupe) {
            array_push($groupesAChoisir, $sousGroupe);
        }
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
                'choices' => $groupesAChoisir // On choisira parmis les sous groupes du groupe choisi au préalable
            ])
            ->getForm();

        $effectifsParStatut = array();
        if ($typeGraph == "evolutionStatut") {
            $statutChoisi = $session->get('statut');
            foreach ($groupesAChoisir as $groupeAChoisir) {
                array_push($effectifsParStatut, count($repoEtudiant->findAllByOneStatutAndOneGroupe($statutChoisi, $groupeAChoisir)));
            }
        }

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($request->getSession()->get('typeGraphique') == "evolutionGroupe") {
                if (count($form->get('groupes')->getData()) > 0) {
                    $sousGroupes = $form->get('groupes')->getData();
                    $request->getSession()->set('sousGroupes', $sousGroupes);
                    return $this->redirectToRoute('statistiques_groupes_choisir_plusieurs_evaluations', ['slug' => $groupe->getSlug()]);
                }
            } elseif ($request->getSession()->get('typeGraphique') == "evolutionStatut") {
                if (count($form->get('groupes')->getData()) > 0) {
                    $sousGroupes = $form->get('groupes')->getData();
                    $request->getSession()->set('sousGroupes', $sousGroupes);
                    return $this->redirectToRoute('statistiques_groupes_choisir_plusieurs_evaluations', ['slug' => $groupe->getSlug()]);
                }
            } else {
                $sousGroupes = $form->get('groupes')->getData();
                $request->getSession()->set('sousGroupes', $sousGroupes);
                return $this->redirectToRoute('statistiques_groupes_choisir_plusieurs_evaluations', ['slug' => $groupe->getSlug()]);
            }
        }
        return $this->render('statistiques/choix_sous-groupes_plusieurs_evals.html.twig', [
            'groupe' => $groupe,
            'pasDIndentation' => false,
            'form' => $form->createView(),
            'effectifsParStatut' => $effectifsParStatut,
            'typeGraph' => $typeGraph,
            'statut' => $statut

        ]);
    }

    /**
     * @Route("/plusieurs-eval/evolution-de-resultats/{slug}/", name="determiner_evolution_etudiants_groupe")
     */
    public function determinerEvolutionEtudiantGroupes(Request $request, PointsRepository $repoPoints, StatutRepository $repoStatut, GroupeEtudiantRepository $repoGroupe, EtudiantRepository $repoEtudiant, EvaluationRepository $repoEval): Response
    {
        $session = $request->getSession();
        $typeGraph = $request->getSession()->get('typeGraphique');

        if ($typeGraph == "evolutionGroupe") {
            $type = "groupe";
        } else {
            $type = "statut";
            $session->get('statut');
        }
        $groupes = $session->get('lesGroupes'); // récupération des groupes passés en session
        $tabGroupes = array();
        foreach ($groupes as $groupe) {
            array_push($tabGroupes, $repoGroupe->find($groupe->getId()));
        }
        $tabStatut = array();
        if ($type == "statut") {
            $statut = $session->get('statut');
            array_push($tabStatut, $repoStatut->find($statut));
        }

        $evaluations = $session->get('evaluations'); // récupération des évaluations passées en session
        $tabEvaluations = array();
        foreach ($evaluations as $evaluation) {
            array_push($tabEvaluations, $repoEval->find($evaluation->getId()));
        }

        usort($tabEvaluations, function ($a, $b) {
            if ($a->getdate() == $b->getdate()) {
                return 0;
            }
            return ($a->getdate() < $b->getdate()) ? -1 : 1;
        });

        /// génération des statistiques
        $stats = array(); // le tableau qui contiendra toutes les données exploitables par le typeGraph
        $typeGroupe = array();
        $typeGroupe["type"] = $type;

        if ($type == "statut") {
            array_push($typeGroupe, $tabStatut[0]);
        }

        $stats["typeGroupe"] = $typeGroupe;
        $stats["evaluations"] = $tabEvaluations;

        if ($type == "groupe") { // le traiteme,nt concerne un ou des groupes d'étudiants
            foreach ($tabGroupes as $groupe) {
                $groupeEtudiant = array();
                $etudiants = array();
                $recupEtudiantsGroupe = $groupe->getEtudiants();
                $groupeEtudiant["nom"] = $groupe->getNom();

                foreach ($recupEtudiantsGroupe as $etudiant) {
                    $notesEtudiant = array();
                    $etudiantCourant = array();
                    $etudiantCourant["nomPrenom"] = strval($etudiant->getNom() . " " . $etudiant->getPrenom());

                    foreach ($tabEvaluations as $evaluation) {
                        $notesEtEtudiants = $repoPoints->findNotesAndEtudiantByEvaluation($evaluation);
                        $etudiantsEvaluation = array();
                        foreach ($notesEtEtudiants as $note) {
                            array_push($etudiantsEvaluation, $note->getEtudiant());
                        }

                        foreach ($notesEtEtudiants as $points) {
                            if ($points->getEtudiant() == $etudiant) {
                                array_push($notesEtudiant, $points->getValeur());
                            }
                        }
                        if (!in_array($etudiant, $etudiantsEvaluation)) {
                            array_push($notesEtudiant, "NaN");
                        }
                        $etudiantCourant["notes"] = $notesEtudiant; // on pousse les notes de l'étudiant courant
                    }
                    array_push($etudiants, $etudiantCourant); //on pousse l'étudiant
                }
                $groupeEtudiant["etudiants"] = $etudiants;
                array_push($stats, $groupeEtudiant);
            }
        } else {
            foreach ($tabStatut as $statut) {

                foreach ($tabGroupes as $groupe) {
                    $groupeStatutEtudiant = array();
                    $etudiants = array();
                    $recupEtudiantsGroupeAvecStatut = $repoEtudiant->findAllByOneStatutAndOneGroupe($tabStatut[0], $groupe); /// REQUETE PERSONNALIS2
                    $groupeStatutEtudiant["nom"] = $groupe->getNom();

                    foreach ($recupEtudiantsGroupeAvecStatut as $etudiant) {
                        $notesEtudiant = array();
                        $etudiantCourant = array();
                        $etudiantCourant["nomPrenom"] = strval($etudiant->getNom() . " " . $etudiant->getPrenom());

                        foreach ($tabEvaluations as $evaluation) {
                            $notesEtEtudiants = $repoPoints->findNotesAndEtudiantByEvaluation($evaluation);
                            $etudiantsEvaluation = array();
                            foreach ($notesEtEtudiants as $note) {
                                array_push($etudiantsEvaluation, $note->getEtudiant());
                            }

                            foreach ($notesEtEtudiants as $points) {
                                if ($points->getEtudiant() == $etudiant) {
                                    array_push($notesEtudiant, $points->getValeur());
                                }
                            }
                            if (!in_array($etudiant, $etudiantsEvaluation)) {
                                array_push($notesEtudiant, "NaN");
                            }
                            $etudiantCourant["notes"] = $notesEtudiant; // on pousse les notes de l'étudiant courant
                        }
                        array_push($etudiants, $etudiantCourant); //on pousse l'étudiant
                    }
                    $groupeStatutEtudiant["etudiants"] = $etudiants;
                    array_push($stats, $groupeStatutEtudiant);
                }
            }
        }
        if ($type == "groupe") {
            $groupes = $tabGroupes;
            $titre = "Évolution chronologique des résultats d’un ensemble d’étudiants";
        } else {
            $groupes = $tabStatut;
            $titre = "Évolution chronologique des résultats d’un ensemble d’étudiants appartenant à un statut";
        }
        return $this->render('statistiques/statsEvolution.html.twig', [
            'evaluations' => $tabEvaluations,
            'groupes' => $groupes,
            'titre' => $titre,
            'stats' => $stats
        ]);
    }

    /**
     * @Route("/plusieurs-eval/groupes/{slug}/choisir-evaluations/", name="statistiques_groupes_choisir_plusieurs_evaluations")
     */
    public function choisirEvalsGroupePlusieursEvals(Request $request, StatisticsManager $statsManager, GroupeEtudiant $groupe, PointsRepository $repoPoints): Response
    {
        $typeGraph = $request->getSession()->get('typeGraphique');   // Récupération du type de stat dans la session
        $statut = null; //initialisation de la variable qui herbergera le stautut si le type de stat le requiert
        if ($typeGraph == "evolutionStatut") {
            $statut = $request->getSession()->get('statut');
        }
        $form = $this->createFormBuilder()
            ->add('evaluations', EntityType::class, [
                'class' => Evaluation::Class,
                'choice_label' => false,
                'label' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $groupe->getEvaluations() // On choisira parmis les evaluations du groupe principal
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
            if ($typeGraph == "evolutionGroupe" or $typeGraph == "evolutionStatut") {
                $request->getSession()->set('evaluations', $evaluations);
                $request->getSession()->set('lesGroupes', $lesGroupes);
                return $this->redirectToRoute("determiner_evolution_etudiants_groupe", [
                    'slug' => $groupe->getSlug()
                ]);
            } else {
                foreach ($lesGroupes as $groupe) // On récupère les notes du groupe principal et des sous groupes sur toutes les évaluations choisis
                {
                    $tabPoints = array();
                    foreach ($evaluations as $eval) {
                        array_push($tabPoints, $repoPoints->findAllNotesByGroupe($eval->getId(), $groupe->getId()));
                    }
                    //On crée une copie de tabPoints qui contiendra les valeurs des notes pour simplifier le tableau renvoyé par la requete
                    $copieTabPoints = array();
                    foreach ($tabPoints as $element) {
                        foreach ($element as $point) {
                            foreach ($point as $note) {
                                $copieTabPoints[] = $note;
                            }
                        }
                    }
                    //On remplit le tableau qui contiendra toutes les statistiques du groupe
                    $listeStatsParGroupe[] = array("nom" => $groupe->getNom(),
                        "repartition" => $statsManager->repartition($copieTabPoints),
                        "listeNotes" => $copieTabPoints,
                        "moyenne" => $statsManager->moyenne($copieTabPoints),
                        "ecartType" => $statsManager->ecartType($copieTabPoints),
                        "minimum" => $statsManager->minimum($copieTabPoints),
                        "maximum" => $statsManager->maximum($copieTabPoints),
                        "mediane" => $statsManager->mediane($copieTabPoints)
                    );
                }
                $formatStatsPourLaVue = [["nom" => "Évaluations", "bareme" => 20, "stats" => $listeStatsParGroupe]];
                return $this->render('statistiques/stats.html.twig', [
                    'parties' => $formatStatsPourLaVue,
                    'evaluations' => $evaluations,
                    'groupes' => $lesGroupes,
                    'titrePage' => 'Consulter les statistiques sur ' . count($evaluations) . ' évaluation(s)',
                    'plusieursEvals' => true,
                ]);
            }
        }
        return $this->render('statistiques/choix_evals_plusieurs_evals_groupes.html.twig', [
            'form' => $form->createView(),
            'typeGraph' => $typeGraph,
            'statut' => $statut
        ]);
    }

    /**
     * @Route("/plusieurs-eval/statuts/{typeGraph}/choisir-statut", name="stats_choisir_statut")
     */
    public function choisirStatutsEvaluable(Request $request, StatutRepository $repoStatut, $typeGraph): Response
    {
        $session = $request->getSession();
        //On met en sesssion le type de graphique choisi par l'utilisateur pour afficher l'onglet correspondant lors de l'affichage des stats
        $request->getSession()->set('typeGraphique', $typeGraph);
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
            if ($typeGraph == "evolutionStatut") {
                $session->set('statut', $form->get('groupes')->getData());
                return $this->redirectToRoute('stats_choisir_groupe_haut_niveau_evaluable', [
                    'typeGraph' => $typeGraph
                ]);
            } else {
                return $this->redirectToRoute('statistiques_statut_choisir_plusieurs_evaluations', [
                    'slug' => $form->get('groupes')->getData()->getSlug()
                ]);
            }
        }
        return $this->render('statistiques/choix_statut_plusieurs_evals.html.twig', [
            'form' => $form->createView(),
            'pasDIndentation' => true,
            'typeGraph' => $typeGraph
        ]);
    }

    /**
     * @Route("/plusieurs-eval/statuts/{slug}/choisir-evaluations/", name="statistiques_statut_choisir_plusieurs_evaluations")
     */
    public function choisirEvalsStatutPlusieursEvals(Request $request, StatisticsManager $statsManager, Statut $statut, EvaluationRepository $repoEval, PointsRepository $repoPoints): Response
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
            $listeStatsParStatut = array(); // On initialise un tableau vide qui contiendra les statistiques du statut choisi
            $tabPoints = array();
            foreach ($evaluations as $eval) {
                array_push($tabPoints, $repoPoints->findAllNotesByStatut($eval->getId(), $statut->getId()));
            }
            //On crée une copie de tabPoints qui contiendra les valeurs des notes pour simplifier le tableau renvoyé par la requete
            $copieTabPoints = array();
            foreach ($tabPoints as $element) {
                foreach ($element as $point) {
                    foreach ($point as $note) {
                        $copieTabPoints[] = $note;
                    }
                }
            }
            //On remplit le tableau qui contiendra toutes les statistiques du groupe
            $listeStatsParStatut[] = array("nom" => $statut->getNom(),
                "repartition" => $statsManager->repartition($copieTabPoints),
                "listeNotes" => $copieTabPoints,
                "moyenne" => $statsManager->moyenne($copieTabPoints),
                "ecartType" => $statsManager->ecartType($copieTabPoints),
                "minimum" => $statsManager->minimum($copieTabPoints),
                "maximum" => $statsManager->maximum($copieTabPoints),
                "mediane" => $statsManager->mediane($copieTabPoints)
            );
            $formatStatsPourLaVue = [["nom" => "Évaluations", "bareme" => 20, "stats" => $listeStatsParStatut]];
            return $this->render('statistiques/stats.html.twig', [
                'parties' => $formatStatsPourLaVue,
                'evaluations' => $evaluations,
                'groupes' => $statut,
                'titrePage' => 'Consulter les statistiques sur ' . count($evaluations) . ' évaluation(s)',
                'plusieursEvals' => true,
            ]);
        }
        return $this->render('statistiques/choix_evals_plusieurs_evals_statut.html.twig', [
            'form' => $form->createView(),
            'statut' => $statut
        ]);
    }

    /**
     * @Route("/fiche-etudiant/choix-etudiant", name="statistiques_fiche_etudiant_choisir_etudiant")
     */
    public function choisirEtudiantFicheEtudiant(Request $request, StatisticsManager $statsManager, EvaluationRepository $repoEval, EtudiantRepository $repoEtudiant, PointsRepository $repoPoint): Response
    {
        $form = $this->createFormBuilder()
            ->add('etudiants', EntityType::class, [
                'class' => Etudiant::Class, //On veut choisir un étudiant
                'choice_label' => false, // On n'affichera pas d'attribut de l'entité à côté du bouton pour aider au choix car on liste les entités en utilisant les variables du champ
                'label' => false, // On n'affiche pas le label du champ
                'expanded' => true, // Pour avoir des boutons
                'multiple' => false, // radios
                'choices' => $repoEtudiant->findAllConcernedByAtLeastOneEvaluation(), // On choisira parmis tous les étudiants
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
                    array_push($groupesEtStatuts, $groupe);
                    array_push($groupes, $groupe);
                }
            }
            foreach ($etudiant->getStatuts() as $statut) {
                array_push($groupesEtStatuts, $statut);
                array_push($statuts, $statut);
            }
            $toutesLesStats = array();
            foreach ($evaluations as $eval) {
                foreach ($groupes as $groupe) {
                    // On récupère le classement de l'étudiant dans le groupe et sa note
                    $tabRang = $repoPoint->findAllNotesByGroupe($eval->getId(), $groupe->getId());
                    $copieTabRang = array();
                    foreach ($tabRang as $element) {
                        $copieTabRang[] = $element["valeur"];
                    }
                    $effectif = sizeof($copieTabRang);
                    $noteEtudiant = $repoPoint->findNoteByEvalAndStudent($eval->getId(), $etudiant->getId())[0]['valeur'];
                    $position = array_search($noteEtudiant, $copieTabRang) + 1;
                    $classement = strval($position) . " / " . strval($effectif);
                    //On récupère la moyenne du groupe
                    $tabPoints = array();
                    array_push($tabPoints, $repoPoint->findAllNotesByGroupe($eval->getId(), $groupe->getId()));
                    //On crée une copie de tabPoints qui contiendra les valeurs des notes pour simplifier le tableau renvoyé par la requete
                    $copieTabPoints = array();
                    foreach ($tabPoints as $element) {
                        foreach ($element as $point) {
                            foreach ($point as $note) {
                                $copieTabPoints[] = $note;
                            }
                        }
                    }
                    $toutesLesStats[] = array(
                        "eval" => $eval->getNom(),
                        "groupe" => $groupe->getNom(),
                        "position" => $classement,
                        "moyenneGroupe" => $statsManager->moyenne($copieTabPoints),
                        "noteEtudiant" => $noteEtudiant
                    );
                }
                foreach ($statuts as $statut) {
                    // On récupère le classement de l'étudiant dans le groupe et sa note
                    $tabRang = $repoPoint->findAllNotesByStatut($eval->getId(), $statut->getId());
                    $copieTabRang = array();
                    foreach ($tabRang as $element) {
                        $copieTabRang[] = $element["valeur"];
                    }
                    $effectif = sizeof($copieTabRang);
                    $noteEtudiant = $repoPoint->findNoteByEvalAndStudent($eval->getId(), $etudiant->getId())[0]['valeur'];
                    $position = array_search($noteEtudiant, $copieTabRang) + 1;
                    $classement = strval($position) . " / " . strval($effectif);
                    //On récupère la moyenne du groupe
                    $tabPoints = array();
                    array_push($tabPoints, $repoPoint->findAllNotesByStatut($eval->getId(), $statut->getId()));
                    //On crée une copie de tabPoints qui contiendra les valeurs des notes pour simplifier le tableau renvoyé par la requete
                    $copieTabPoints = array();
                    foreach ($tabPoints as $element) {
                        foreach ($element as $point) {
                            foreach ($point as $note) {
                                $copieTabPoints[] = $note;
                            }
                        }
                    }
                    $toutesLesStats[] = array(
                        "eval" => $eval->getNom(),
                        "groupe" => $statut->getNom(),
                        "position" => $classement,
                        "moyenneGroupe" => $statsManager->moyenne($copieTabPoints),
                        "noteEtudiant" => $noteEtudiant
                    );
                }
            }
            return $this->render('statistiques/statsFicheEtudiant.html.twig', [
                'etudiant' => $etudiant,
                'evaluations' => $evaluations,
                'groupesEtStatuts' => $groupesEtStatuts,
                'stats' => $toutesLesStats,
                'titre' => 'Fiche de l\'étudiant ' . $etudiant->getPrenom() . ' ' . $etudiant->getNom()
            ]);
        }
        return $this->render('statistiques/choix_etudiant_fiche_etudiant.html.twig', [
            'form' => $form->createView()
        ]);
    }
}
