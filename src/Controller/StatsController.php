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
     * @Route("/eval-simple/{typeGraphique}/choix-evaluation", name="eval_simple_choix_evaluation", methods={"GET", "POST"})
     */
    public function evalSimpleChoixEvaluation($typeGraphique, EvaluationRepository $repoEval, Request $request) : Response
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
             return $this->redirectToRoute('eval_simple_choix_parametres_et_afficher_stats', [
                'slug' => $form->get('evaluations')->getData()->getSlug()
             ]);
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 1,
            'titrePage' => 'Analyse d’une évaluation simple',
            'activerToutSelectionner' => false,
            'colorationEffectif' => false,
            'typeForm1' => 'evaluations',
            'conditionAffichageForm1' => true,
            'sousTitreForm1' => 'Choisir l\'évaluation pour laquelle vous désirez consulter les statistiques',
        ]);
    }

    /**
     * @Route("/eval-simple/{slug}/choisir-groupes-et-statuts", name="eval_simple_choix_parametres_et_afficher_stats", methods={"GET","POST"})
     */
    public function evalSimplechoisirParametresEtAfficherStats(Request $request, StatisticsManager $statsManager, Evaluation $evaluation, StatutRepository $repoStatut, GroupeEtudiantRepository $repoGroupe, PointsRepository $repoPoints): Response
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
                //$session->set('stats', $toutesLesStats);
                return $this->render('statistiques/stats.html.twig', [
                    'titrePage' => 'Statistiques pour ' . $evaluation->getNom(),
                    'plusieursEvals' => false,
                    'evaluation' => $evaluation,
                    'parties' => $toutesLesStats
                ]);
            }
        }
        return $this->render('statistiques/formulaire_parametrage_statistiques.html.twig', [
            'form' => $form->createView(),
            'nbForm' => 2,
            'activerToutSelectionner' => true,
            'titrePage' => "Analyse d’une évaluation simple (" . $evaluation->getNom() . ")",
            'colorationEffectif' => false,
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

}
