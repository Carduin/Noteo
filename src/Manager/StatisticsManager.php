<?php

namespace App\Manager;

use App\Repository\PointsRepository;

class StatisticsManager {

    private $repoPoints;

    public function __construct(PointsRepository $repoPoints) {
        $this->repoPoints = $repoPoints;
    }

    public function calculerStats($mode = '', $evaluations = [], $groupes = [], $statuts = [], $parties = []) {
        $toutesLesStats = [];
        switch ($mode) {
            case 'classique' :
                foreach ($parties as $partie) {
                    $statsDuGroupePourLaPartie = [];
                    foreach ($groupes as $groupe) {
                        $notesGroupe = $this->repoPoints->findByGroupeAndPartie($evaluations[0]->getId(), $groupe->getId(), $partie->getId());
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
                        $notesStatut = $this->repoPoints->findByStatutAndPartie($evaluations[0]->getId(), $statut->getId(), $partie->getId());
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
                    $toutesLesStats[] = [
                        "nom" => $partie->getIntitule(),
                        "bareme" => $partie->getBareme(),
                        "stats" => array_merge($statsDuGroupePourLaPartie, $statsDuStatutPourLaPartie)
                    ];
                }
                break;
        }
        return $toutesLesStats;
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
