<?php

namespace App\Manager;

use App\Repository\PointsRepository;

class StatisticsManager {

    private $repoPoints;

    public function __construct(PointsRepository $repoPoints) {
        $this->repoPoints = $repoPoints;
    }

    public function calculerStatsFicheEtudiant($etudiant = [], $evaluations = [], $groupes = [], $statuts = []) {
        $statistiques = array();
        foreach ($evaluations as $eval) {
            foreach ($groupes as $groupe) {
                // On récupère le classement de l'étudiant dans le groupe et sa note
                $tabRang = $this->repoPoints->findAllNotesByGroupe($eval->getId(), $groupe->getId());
                $copieTabRang = array();
                foreach ($tabRang as $element) {
                    $copieTabRang[] = $element["valeur"];
                }
                $effectif = sizeof($copieTabRang);
                $noteEtudiant = $this->repoPoints->findNoteByEvalAndStudent($eval->getId(), $etudiant->getId())[0]['valeur'];
                $position = array_search($noteEtudiant, $copieTabRang) + 1;
                $classement = strval($position) . " / " . strval($effectif);
                //On récupère la moyenne du groupe
                $tabPoints = array();
                array_push($tabPoints, $this->repoPoints->findAllNotesByGroupe($eval->getId(), $groupe->getId()));
                //On crée une copie de tabPoints qui contiendra les valeurs des notes pour simplifier le tableau renvoyé par la requete
                $copieTabPoints = array();
                foreach ($tabPoints as $element) {
                    foreach ($element as $point) {
                        foreach ($point as $note) {
                            $copieTabPoints[] = $note;
                        }
                    }
                }
                $statistiques[] = array(
                    "eval" => $eval->getNom(),
                    "groupe" => $groupe->getNom(),
                    "position" => $classement,
                    "moyenneGroupe" => $this->moyenne($copieTabPoints),
                    "noteEtudiant" => $noteEtudiant
                );
            }
            foreach ($statuts as $statut) {
                // On récupère le classement de l'étudiant dans le groupe et sa note
                $tabRang = $this->repoPoints->findAllNotesByStatut($eval->getId(), $statut->getId());
                $copieTabRang = array();
                foreach ($tabRang as $element) {
                    $copieTabRang[] = $element["valeur"];
                }
                $effectif = sizeof($copieTabRang);
                $noteEtudiant = $this->repoPoints->findNoteByEvalAndStudent($eval->getId(), $etudiant->getId())[0]['valeur'];
                $position = array_search($noteEtudiant, $copieTabRang) + 1;
                $classement = strval($position) . " / " . strval($effectif);
                //On récupère la moyenne du groupe
                $tabPoints = array();
                array_push($tabPoints, $this->repoPoints->findAllNotesByStatut($eval->getId(), $statut->getId()));
                //On crée une copie de tabPoints qui contiendra les valeurs des notes pour simplifier le tableau renvoyé par la requete
                $copieTabPoints = array();
                foreach ($tabPoints as $element) {
                    foreach ($element as $point) {
                        foreach ($point as $note) {
                            $copieTabPoints[] = $note;
                        }
                    }
                }
                $statistiques[] = array(
                    "eval" => $eval->getNom(),
                    "groupe" => $statut->getNom(),
                    "position" => $classement,
                    "moyenneGroupe" => $this->moyenne($copieTabPoints),
                    "noteEtudiant" => $noteEtudiant
                );
            }
        }
        return $statistiques;
    }

    public function calculerStatsComparaison($evaluation = [], $groupes = [], $statuts = [], $autresEvaluations=[] ) {
        $statistiques = [];
        $tabStatsComparaison = array();
        foreach ($groupes as $groupe) {
            // déterminer la moyenne du groupe courant à l'évaluation de référence
            $pointsEvaluationGroupe = $this->repoPoints->findAllNotesByGroupe($evaluation->getId(), $groupe->getId());
            $moyenneEvaluationGroupe = array();
            foreach ($pointsEvaluationGroupe as $note) {
                $moyenneEvaluationGroupe[] = $note["valeur"];
            }
            $moyenneEvaluationCouranteGroupe = $this->moyenne($moyenneEvaluationGroupe);
            //déterminer la moyenne des moyennes aux évaluations
            $moyennesGroupeTmp = array();
            foreach ($autresEvaluations as $evaluationCourante) { // pour chaque évaluation, on détermine sa moyenne pour le groupe courant
                //determiner la moyenne de l'évaluation courante
                $tabPoints = $this->repoPoints->findAllNotesByGroupe($evaluationCourante->getId(), $groupe->getId()); // on récupère les notes
                //on crée un tableau temporaire ou on stoque séparement chaque note
                $copieTab = array();
                foreach ($tabPoints as $note) {
                    $copieTab[] = $note["valeur"];
                }
                $moyenneEvaluationCourante = $this->moyenne($copieTab); // on determine la moyenne du controle courant
                array_push($moyennesGroupeTmp, $moyenneEvaluationCourante);
            }
            //on détermine la moyenne des moyennes
            $moyenneDesMoyennesEvaluations = $this->moyenne($moyennesGroupeTmp);
            $tabStatsComparaison[] = [
                "nom" => $groupe->getNom(),
                "moyenneControleCourant" => $moyenneEvaluationCouranteGroupe,
                "moyenneAutresControles" => $moyenneDesMoyennesEvaluations,
            ];
        }
        ///on traite les statistiques pour tous les statuts
        foreach ($statuts as $statut) {
            /// déterminer la moyenne du groupe courant à l'évaluation
            $pointsEvaluationStatut = $this->repoPoints->findAllNotesByStatut($evaluation->getId(), $statut->getId());
            $moyenneEvaluationCouranteStatut = array();
            foreach ($pointsEvaluationStatut as $note) {
                $moyenneEvaluationCouranteStatut[] = $note["valeur"];
            }
            $moyenneEvaluationCouranteStatut = $this->moyenne($moyenneEvaluationCouranteStatut);
            /// déterminer la moyenne des moyennes aux évaluations
            $moyennesTmp = array();

            foreach ($autresEvaluations as $evaluationCourante) { // pour chaque évaluation, on détermine sa moyenne pour le groupe courant
                //determiner la moyenne de l'évaluation courante
                $tabPoints = $this->repoPoints->findAllNotesByStatut($evaluationCourante->getId(), $statut->getId()); // on récupère les notes
                //on crée un tableau temporaire ou on stoque séparement chaque note
                $copieTab = array();
                foreach ($tabPoints as $note) {
                    $copieTab[] = $note["valeur"];
                }
                $moyenneEvaluationCourante = $this->moyenne($copieTab); // on determine la moyenne du controle courant
                array_push($moyennesTmp, $moyenneEvaluationCourante);
            }
            //on détermine la moyenne des moyennes
            $moyenneDesMoyennesEvaluations = $this->moyenne($moyennesTmp);
            $tabStatsComparaison[] = [
                "nom" => $statut->getNom(),
                "moyenneControleCourant" => $moyenneEvaluationCouranteStatut,
                "moyenneAutresControles" => $moyenneDesMoyennesEvaluations,
            ];
        }
        $statistiques = [[
            "nom" => "Comparaison de " . $evaluation->getNom() . " à " . (count($autresEvaluations)) . ' évaluation(s)',
            "stats" => $tabStatsComparaison
        ]];

        return $statistiques;
    }

    public function calculerStatsClassiques($evaluation = [], $groupes = [], $statuts = [], $parties = []) {
        $statistiques = [];
        foreach ($parties as $partie) {
            $statsDuGroupePourLaPartie = [];
            foreach ($groupes as $groupe) {
                $notesGroupe = $this->repoPoints->findByGroupeAndPartie($evaluation->getId(), $groupe->getId(), $partie->getId());
                //On fait une copie du résultat de la requête pour simplifier le format de renvoi utilisé par doctrine
                $copieTabPoints = array();
                foreach ($notesGroupe as $element) {
                    $copieTabPoints[] = $element["valeur"];
                }
                $statsDuGroupePourLaPartie[] = [
                    "nom" => $groupe->getNom(),
                    "repartition" => $this->repartition($copieTabPoints),
                    "listeNotes" => $copieTabPoints,
                    "moyenne" => $this->moyenne($copieTabPoints),
                    "ecartType" => $this->ecartType($copieTabPoints),
                    "minimum" => $this->minimum($copieTabPoints),
                    "maximum" => $this->maximum($copieTabPoints),
                    "mediane" => $this->mediane($copieTabPoints),
                ];
            }
            $statsDuStatutPourLaPartie = [];
            foreach ($statuts as $statut) {
                $notesStatut = $this->repoPoints->findByStatutAndPartie($evaluation->getId(), $statut->getId(), $partie->getId());
                //On fait une copie du résultat de la requête pour simplifier le format de renvoi utilisé par doctrine
                $copieTabPoints = array();
                foreach ($notesStatut as $element) {
                    $copieTabPoints[] = $element["valeur"];
                }
                $statsDuStatutPourLaPartie[] = [
                    "nom" => $statut->getNom(),
                    "repartition" => $this->repartition($copieTabPoints),
                    "listeNotes" => $copieTabPoints,
                    "moyenne" => $this->moyenne($copieTabPoints),
                    "ecartType" => $this->ecartType($copieTabPoints),
                    "minimum" => $this->minimum($copieTabPoints),
                    "maximum" => $this->maximum($copieTabPoints),
                    "mediane" => $this->mediane($copieTabPoints),
                ];
            }
            //Ajout des stats de la partie (groupe + statut) dans le tableau général
            $statistiques[] = [
                "nom" => $partie->getIntitule(),
                "bareme" => $partie->getBareme(),
                "stats" => array_merge($statsDuGroupePourLaPartie, $statsDuStatutPourLaPartie)
            ];
        }
        return $statistiques;

    }

    public function repartition($tabPoints)
    {
        $repartition = array(0, 0, 0, 0, 0);
        foreach ($tabPoints as $note) {
            if ($note >= 0 && $note < 4) {
                $repartition[0]++;
            }
            if ($note >= 4 && $note < 8) {
                $repartition[1]++;
            }
            if ($note >= 8 && $note < 12) {
                $repartition[2]++;
            }
            if ($note >= 12 && $note < 16) {
                $repartition[3]++;
            }
            if ($note >= 16 && $note <= 20) {
                $repartition[4]++;
            }
        }
        return $repartition;
    }

    public function moyenne($tabPoints)
    {
        $moyenne = 0;
        $nbNotes = 0;
        foreach ($tabPoints as $note) {
            $nbNotes++;
            $moyenne += $note;
        }
        if ($nbNotes != 0) {
            $moyenne = $moyenne / $nbNotes;
        } else {
            $moyenne = 0;
        }
        return round($moyenne, 2);
    }

    public function ecartType($tabPoints)
    {
        $moyenne = $this->moyenne($tabPoints);
        $nbNotes = 0;
        $ecartType = 0;
        foreach ($tabPoints as $note) {
            $ecartType = $ecartType + pow(($note - $moyenne), 2);
            $nbNotes++;
        }
        if ($nbNotes != 0) {
            $ecartType = sqrt($ecartType / $nbNotes);
        } else {
            $ecartType = 0;
        }
        return round($ecartType, 2);
    }

    public function minimum($tabPoints)
    {
        if (!empty($tabPoints)) {
            $min = 20;
            foreach ($tabPoints as $note) {
                if ($note < $min) {
                    $min = $note;
                }
            }
        } else {
            $min = 0;
        }
        return $min;
    }

    public function maximum($tabPoints)
    {
        $max = 0;
        foreach ($tabPoints as $note) {
            if ($note > $max) {
                $max = $note;
            }
        }
        return $max;
    }

    public function mediane($tabPoints)
    {
        $mediane = 0;
        $nbValeurs = count($tabPoints);
        if (!empty($tabPoints)) {
            if ($nbValeurs % 2 == 1) //Si il y a un nombre impair de valeurs, la médiane vaut la valeur au milieu du tableau
            {
                $mediane = $tabPoints[intval($nbValeurs / 2)];
            } else //Si c'est pair, la mediane vaut la demi-somme des 2 valeurs centrales
            {
                $mediane = ($tabPoints[($nbValeurs - 1) / 2] + $tabPoints[($nbValeurs) / 2]) / 2;
            }
        } else {
            $mediane = 0;
        }
        return $mediane;
    }


}
