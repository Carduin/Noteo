<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

use App\Entity\Enseignant;
use App\Entity\Etudiant;
use App\Entity\Evaluation;
use App\Entity\GroupeEtudiant;
use App\Entity\Partie;
use App\Entity\Points;
use App\Entity\Statut;


class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $faker = \Faker\Factory::create('fr_FR');
        $nbEtudiantsParGroupes = 15;
        //Admin Lié aux groupes
        $admin = new Enseignant();
        $admin->setPrenom('Patrick');
        $admin->setNom('Etcheverry');
        $admin->setEmail('patrick.etcheverry@iutbayonne.univ-pau.fr');
        $admin->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $admin->setPassword('$2y$10$iq3Tby/8xdfCtQiPk/IQKO5j9xypK/uej1cghWXEZmQl1D9OHJaNC'); // patrick_admin
        $admin->setPreferenceNbElementsTableaux(-1);
        $manager->persist($admin);
        //Compte enseignant jury
        $yann = new Enseignant();
        $yann->setPrenom('Yann');
        $yann->setNom('Carpentier');
        $yann->setEmail('yann.carpentier@iutbayonne.univ-pau.fr');
        $yann->setRoles(['ROLE_USER']);
        $yann->setPassword('$2y$14$eecC8BxE2hUdovPKkStY6epSxnsHUS.V7TQhoARgHGwofz16VbGvu'); // yann_prof
        $yann->setPreferenceNbElementsTableaux(30);
        $manager->persist($yann);
        //Compte enseignant jury
        $marie = new Enseignant();
        $marie->setPrenom('Marie');
        $marie->setNom('Bruyère');
        $marie->setEmail('marie.bruyere@iutbayonne.univ-pau.fr');
        $marie->setRoles(['ROLE_USER']);
        $marie->setPassword('$2y$14$UDM9JS7jEzoGiPV.upVXI.8uNbr2J7xbCsalNX7m3n6PI0Ul4VnoC'); // marie_prof
        $marie->setPreferenceNbElementsTableaux(15);
        $manager->persist($marie);
        ////////////GROUPES//////////////
        ////////////ESPACE//////////////
        $espace = new GroupeEtudiant();
        $espace->setNom('Etudiants non affectés');
        $espace->setDescription('Tout les étudiants ayant été retirés d\'un groupe de haut niveau et ne faisant partie d\'aucun groupe');
        $espace->setEnseignant($admin);
        $espace->setEstEvaluable(false);
        ////////////RACINE//////////////
        $DUT = new GroupeEtudiant();
        $DUT->setNom('DUT Info');
        $DUT->setDescription('Tout les étudiants du DUT Informatique de l\'IUT');
        $DUT->setEnseignant($admin);
        $DUT->setEstEvaluable(false);
        ////////////SEMESTRES//////////////
        $S2 = new GroupeEtudiant();
        $S2->setNom('S2');
        $S2->setDescription('Les étudiants du S2 du DUT Info');
        $S2->setParent($DUT);
        $S2->setEnseignant($admin);
        $S2->setEstEvaluable(true);
        ////////////TDs//////////////
        $S2TD1 = new GroupeEtudiant();
        $S2TD1->setNom('TD1');
        $S2TD1->setDescription('Les étudiants du TD1 du S2');
        $S2TD1->setParent($S2);
        $S2TD1->setEnseignant($admin);
        $S2TD1->setEstEvaluable(true);
        ////////////TPs//////////////
        $S2TD1TP1 = new GroupeEtudiant();
        $S2TD1TP1->setNom('TP1');
        $S2TD1TP1->setDescription('Les étudiants du TP1 du TD1 du S2');
        $S2TD1TP1->setParent($S2TD1);
        $S2TD1TP1->setEnseignant($admin);
        $S2TD1TP1->setEstEvaluable(true);
        $S2TD1TP2 = new GroupeEtudiant();
        $S2TD1TP2->setNom('TP2');
        $S2TD1TP2->setDescription('Les étudiants du TP2 du TD1 du S2');
        $S2TD1TP2->setParent($S2TD1);
        $S2TD1TP2->setEnseignant($admin);
        $S2TD1TP2->setEstEvaluable(true);
        $S2TD2 = new GroupeEtudiant();
        $S2TD2->setNom('TD2');
        $S2TD2->setDescription('Les étudiants du TD2 du S2');
        $S2TD2->setParent($S2);
        $S2TD2->setEnseignant($admin);
        $S2TD2->setEstEvaluable(true);
        ////////////TPs//////////////
        $S2TD2TP3 = new GroupeEtudiant();
        $S2TD2TP3->setNom('TP3');
        $S2TD2TP3->setDescription('Les étudiants du TP3 du TD2 du S2');
        $S2TD2TP3->setParent($S2TD2);
        $S2TD2TP3->setEnseignant($admin);
        $S2TD2TP3->setEstEvaluable(true);
        $S2TD2TP4 = new GroupeEtudiant();
        $S2TD2TP4->setNom('TP4');
        $S2TD2TP4->setDescription('Les étudiants du TP4 du TD2 du S2');
        $S2TD2TP4->setParent($S2TD2);
        $S2TD2TP4->setEnseignant($admin);
        $S2TD2TP4->setEstEvaluable(true);
        $S2TD3 = new GroupeEtudiant();
        $S2TD3->setNom('TD3');
        $S2TD3->setDescription('Les étudiants du TD3 du S2');
        $S2TD3->setParent($S2);
        $S2TD3->setEnseignant($admin);
        $S2TD3->setEstEvaluable(true);
        ////////////TPs//////////////
        $S2TD3TP5 = new GroupeEtudiant();
        $S2TD3TP5->setNom('TP5');
        $S2TD3TP5->setDescription('Les étudiants du TP5 du TD3 du S2');
        $S2TD3TP5->setParent($S2TD3);
        $S2TD3TP5->setEnseignant($admin);
        $S2TD3TP5->setEstEvaluable(true);
        $S4 = new GroupeEtudiant();
        $S4->setNom('S4');
        $S4->setDescription('Les étudiants du S4 du DUT Info');
        $S4->setParent($DUT);
        $S4->setEnseignant($admin);
        $S4->setEstEvaluable(true);
        ////////////TDs//////////////
        $IPI = new GroupeEtudiant();
        $IPI->setNom('IPI');
        $IPI->setDescription('Les étudiants du parcours IPI du S4');
        $IPI->setParent($S4);
        $IPI->setEnseignant($admin);
        $IPI->setEstEvaluable(true);
        ////////////TPs//////////////
        $S4IPITP1 = new GroupeEtudiant();
        $S4IPITP1->setNom('TP1');
        $S4IPITP1->setDescription('Les étudiants du TP1 du parcours IPI du S4');
        $S4IPITP1->setParent($IPI);
        $S4IPITP1->setEnseignant($admin);
        $S4IPITP1->setEstEvaluable(true);
        $S4IPITP2 = new GroupeEtudiant();
        $S4IPITP2->setNom('TP2');
        $S4IPITP2->setDescription('Les étudiants du TP2 du parcours IPI du S4');
        $S4IPITP2->setParent($IPI);
        $S4IPITP2->setEnseignant($admin);
        $S4IPITP2->setEstEvaluable(true);
        $PEL = new GroupeEtudiant();
        $PEL->setNom('PEL');
        $PEL->setDescription('Les étudiants du parcours PEL du S4');
        $PEL->setParent($S4);
        $PEL->setEnseignant($admin);
        $PEL->setEstEvaluable(true);
        ////////////TPs//////////////
        $S4PELTP1 = new GroupeEtudiant();
        $S4PELTP1->setNom('TP3');
        $S4PELTP1->setDescription('Les étudiants du TP3 du parcours PEL du S4');
        $S4PELTP1->setParent($PEL);
        $S4PELTP1->setEnseignant($admin);
        $S4PELTP1->setEstEvaluable(true);
        $S4PELTP2 = new GroupeEtudiant();
        $S4PELTP2->setNom('TP4');
        $S4PELTP2->setDescription('Les étudiants du TP4 du parcours PEL du S4');
        $S4PELTP2->setParent($PEL);
        $S4PELTP2->setEnseignant($admin);
        $S4PELTP2->setEstEvaluable(true);
        ////////////TPs//////////////
        $S4PELTP3 = new GroupeEtudiant();
        $S4PELTP3->setNom('TP5');
        $S4PELTP3->setDescription('Les étudiants du TP5 du parcours PEL du S4');
        $S4PELTP3->setParent($PEL);
        $S4PELTP3->setEnseignant($admin);
        $S4PELTP3->setEstEvaluable(true);
        //Enregistrement des groupes
        $manager->persist($espace);
        $manager->persist($S2);
        $manager->persist($S2TD1);
        $manager->persist($S2TD1TP1);
        $manager->persist($S2TD1TP2);
        $manager->persist($S2TD2);
        $manager->persist($S2TD2TP3);
        $manager->persist($S2TD2TP4);
        $manager->persist($S2TD3);
        $manager->persist($S2TD3TP5);
        $manager->persist($S4);
        $manager->persist($IPI);
        $manager->persist($S4IPITP1);
        $manager->persist($S4IPITP2);
        $manager->persist($PEL);
        $manager->persist($S4PELTP1);
        $manager->persist($S4PELTP2);
        $manager->persist($S4PELTP3);
        ////////////EVALUATION S4//////////////
        $evalS4 = new Evaluation();
        $evalS4->setNom("M42 02C (IPI) - Recherche opérationnelle");
        $evalS4->setDate(new \DateTime('2020-02-24'));
        $evalS4->setEnseignant($marie);
        $evalS4->setGroupe($IPI);
        $evalS4->setNotesSaisies(true);
        $manager->persist($evalS4);
        ////////////EVALUATION S2//////////////
        $evalS2 = new Evaluation();
        $evalS2->setNom("M21 03 - Bases de la programmation orientée objets");
        $evalS2->setDate(new \DateTime('2020-03-13'));
        $evalS2->setEnseignant($yann);
        $evalS2->setGroupe($S2);
        $evalS2->setNotesSaisies(true);
        $manager->persist($evalS2);
        ////////////PARTIES//////////////
        $partieS4 = new Partie();
        $partieS4->setIntitule("Évaluation");
        $partieS4->setBareme(20);
        $partieS4->setEvaluation($evalS4);
        $manager->persist($partieS4);
        $souspartie1 = new Partie();
        $souspartie1->setIntitule("Exercice 1");
        $souspartie1->setBareme(10);
        $souspartie1->setEvaluation($evalS4);
        $souspartie1->setParent($partieS4);
        $manager->persist($souspartie1);
        $souspartie2 = new Partie();
        $souspartie2->setIntitule("Exercice 2");
        $souspartie2->setBareme(10);
        $souspartie2->setEvaluation($evalS4);
        $souspartie2->setParent($partieS4);
        $manager->persist($souspartie2);
        $partieS2 = new Partie();
        $partieS2->setIntitule("Évaluation");
        $partieS2->setBareme(20);
        $partieS2->setEvaluation($evalS2);
        $manager->persist($partieS2);
        ////////////ETUDIANTS TP1 S2//////////////
        for ($i = 0; $i < $nbEtudiantsParGroupes; $i++) {
            $etudiant = new Etudiant();
            $etudiant->setPrenom($faker->firstNameMale);
            $etudiant->setNom($faker->lastName);
            $etudiant->setMail($faker->email);
            $etudiant->setEstDemissionaire(false);
            $etudiant->addGroupe($S2TD1TP1);
            $etudiant->addGroupe($S2TD1);
            $etudiant->addGroupe($S2);
            $etudiant->addGroupe($DUT);
            $manager->persist($etudiant);
            ////////////AJOUT DE POINTS A L'EVAL//////////////
            $pointsEtud = new Points();
            $pointsEtud->setValeur($faker->numberBetween($min = 0, $max = 20));
            $pointsEtud->setEtudiant($etudiant);
            $pointsEtud->setPartie($partieS2);
            $manager->persist($pointsEtud);
        }

        ////////////ETUDIANTS TP2 S2//////////////
        for ($i = 0; $i < $nbEtudiantsParGroupes; $i++) {
            $etudiant = new Etudiant();
            $etudiant->setPrenom($faker->firstNameMale);
            $etudiant->setNom($faker->lastName);
            $etudiant->setMail($faker->email);
            $etudiant->setEstDemissionaire(false);
            $etudiant->addGroupe($S2TD1TP2);
            $etudiant->addGroupe($S2TD1);
            $etudiant->addGroupe($S2);
            $etudiant->addGroupe($DUT);
            $manager->persist($etudiant);
            ////////////AJOUT DE POINTS A L'EVAL//////////////
            $pointsEtud = new Points();
            $pointsEtud->setValeur($faker->numberBetween($min = 0, $max = 20));
            $pointsEtud->setEtudiant($etudiant);
            $pointsEtud->setPartie($partieS2);
            $manager->persist($pointsEtud);
        }

        ////////////ETUDIANTS TP3 S2//////////////
        for ($i = 0; $i < $nbEtudiantsParGroupes; $i++) {
            $etudiant = new Etudiant();
            $etudiant->setPrenom($faker->firstNameMale);
            $etudiant->setNom($faker->lastName);
            $etudiant->setMail($faker->email);
            $etudiant->setEstDemissionaire(false);
            $etudiant->addGroupe($S2TD2TP3);
            $etudiant->addGroupe($S2TD2);
            $etudiant->addGroupe($S2);
            $etudiant->addGroupe($DUT);
            $manager->persist($etudiant);
            ////////////AJOUT DE POINTS A L'EVAL//////////////
            $pointsEtud = new Points();
            $pointsEtud->setValeur($faker->numberBetween($min = 0, $max = 20));
            $pointsEtud->setEtudiant($etudiant);
            $pointsEtud->setPartie($partieS2);
            $manager->persist($pointsEtud);
        }

        ////////////ETUDIANTS TP4 S2//////////////
        for ($i = 0; $i < $nbEtudiantsParGroupes; $i++) {
            $etudiant = new Etudiant();
            $etudiant->setPrenom($faker->firstNameMale);
            $etudiant->setNom($faker->lastName);
            $etudiant->setMail($faker->email);
            $etudiant->setEstDemissionaire(false);
            $etudiant->addGroupe($S2TD2TP4);
            $etudiant->addGroupe($S2TD2);
            $etudiant->addGroupe($S2);
            $etudiant->addGroupe($DUT);
            $manager->persist($etudiant);
            ////////////AJOUT DE POINTS A L'EVAL//////////////
            $pointsEtud = new Points();
            $pointsEtud->setValeur($faker->numberBetween($min = 0, $max = 20));
            $pointsEtud->setEtudiant($etudiant);
            $pointsEtud->setPartie($partieS2);
            $manager->persist($pointsEtud);
        }

        ////////////ETUDIANTS TP5 S2//////////////
        for ($i = 0; $i < $nbEtudiantsParGroupes; $i++) {
            $etudiant = new Etudiant();
            $etudiant->setPrenom($faker->firstNameMale);
            $etudiant->setNom($faker->lastName);
            $etudiant->setMail($faker->email);
            $etudiant->setEstDemissionaire(false);
            $etudiant->addGroupe($S2TD3TP5);
            $etudiant->addGroupe($S2TD3);
            $etudiant->addGroupe($S2);
            $etudiant->addGroupe($DUT);
            $manager->persist($etudiant);
            ////////////AJOUT DE POINTS A L'EVAL//////////////
            $pointsEtud = new Points();
            $pointsEtud->setValeur($faker->numberBetween($min = 0, $max = 20));
            $pointsEtud->setEtudiant($etudiant);
            $pointsEtud->setPartie($partieS2);
            $manager->persist($pointsEtud);
        }

        ////////////ETUDIANTS TP1 IPI S4//////////////
        for ($i = 0; $i < $nbEtudiantsParGroupes; $i++) {
            $etudiant = new Etudiant();
            $etudiant->setPrenom($faker->firstNameMale);
            $etudiant->setNom($faker->lastName);
            $etudiant->setMail($faker->email);
            $etudiant->setEstDemissionaire(false);
            $etudiant->addGroupe($S4IPITP1);
            $etudiant->addGroupe($IPI);
            $etudiant->addGroupe($S4);
            $etudiant->addGroupe($DUT);
            $manager->persist($etudiant);
            ////////////AJOUT DE POINTS A L'EVAL//////////////
            $pointsExo1 = $faker->numberBetween($min = 0, $max = 10);
            $pointsExo2 = $faker->numberBetween($min = 0, $max = 10);
            $pointsTotal = $pointsExo1 + $pointsExo2;
            $pointsEtud = new Points();
            $pointsEtud->setValeur($pointsExo1);
            $pointsEtud->setEtudiant($etudiant);
            $pointsEtud->setPartie($souspartie1);
            $manager->persist($pointsEtud);
            $pointsEtud = new Points();
            $pointsEtud->setValeur($pointsExo2);
            $pointsEtud->setEtudiant($etudiant);
            $pointsEtud->setPartie($souspartie2);
            $manager->persist($pointsEtud);
            $pointsEtud = new Points();
            $pointsEtud->setValeur($pointsTotal);
            $pointsEtud->setEtudiant($etudiant);
            $pointsEtud->setPartie($partieS4);
            $manager->persist($pointsEtud);
        }

        ////////////ETUDIANTS TP2 IPI S4//////////////
        for ($i = 0; $i < $nbEtudiantsParGroupes; $i++) {
            $etudiant = new Etudiant();
            $etudiant->setPrenom($faker->firstNameMale);
            $etudiant->setNom($faker->lastName);
            $etudiant->setMail($faker->email);
            $etudiant->setEstDemissionaire(false);
            $etudiant->addGroupe($S4IPITP2);
            $etudiant->addGroupe($IPI);
            $etudiant->addGroupe($S4);
            $etudiant->addGroupe($DUT);
            $manager->persist($etudiant);
            ////////////AJOUT DE POINTS A L'EVAL//////////////
            $pointsExo1 = $faker->numberBetween($min = 0, $max = 10);
            $pointsExo2 = $faker->numberBetween($min = 0, $max = 10);
            $pointsTotal = $pointsExo1 + $pointsExo2;
            $pointsEtud = new Points();
            $pointsEtud->setValeur($pointsExo1);
            $pointsEtud->setEtudiant($etudiant);
            $pointsEtud->setPartie($souspartie1);
            $manager->persist($pointsEtud);
            $pointsEtud = new Points();
            $pointsEtud->setValeur($pointsExo2);
            $pointsEtud->setEtudiant($etudiant);
            $pointsEtud->setPartie($souspartie2);
            $manager->persist($pointsEtud);
            $pointsEtud = new Points();
            $pointsEtud->setValeur($pointsTotal);
            $pointsEtud->setEtudiant($etudiant);
            $pointsEtud->setPartie($partieS4);
            $manager->persist($pointsEtud);
        }

        ////////////ETUDIANTS TP1 PEL S4//////////////
        for ($i = 0; $i < $nbEtudiantsParGroupes; $i++) {
            $etudiant = new Etudiant();
            $etudiant->setPrenom($faker->firstNameMale);
            $etudiant->setNom($faker->lastName);
            $etudiant->setMail($faker->email);
            $etudiant->setEstDemissionaire(false);
            $etudiant->addGroupe($S4PELTP1);
            $etudiant->addGroupe($PEL);
            $etudiant->addGroupe($S4);
            $etudiant->addGroupe($DUT);
            $manager->persist($etudiant);
        }

        ////////////ETUDIANTS TP2 PEL S4//////////////
        for ($i = 0; $i < $nbEtudiantsParGroupes; $i++) {
            $etudiant = new Etudiant();
            $etudiant->setPrenom($faker->firstNameMale);
            $etudiant->setNom($faker->lastName);
            $etudiant->setMail($faker->email);
            $etudiant->setEstDemissionaire(false);
            $etudiant->addGroupe($S4PELTP2);
            $etudiant->addGroupe($PEL);
            $etudiant->addGroupe($S4);
            $etudiant->addGroupe($DUT);
            $manager->persist($etudiant);
        }

        ////////////ETUDIANTS TP3 PEL S4//////////////
        for ($i = 0; $i < $nbEtudiantsParGroupes; $i++) {
            $etudiant = new Etudiant();
            $etudiant->setPrenom($faker->firstNameMale);
            $etudiant->setNom($faker->lastName);
            $etudiant->setMail($faker->email);
            $etudiant->setEstDemissionaire(false);
            $etudiant->addGroupe($S4PELTP3);
            $etudiant->addGroupe($PEL);
            $etudiant->addGroupe($S4);
            $etudiant->addGroupe($DUT);
            $manager->persist($etudiant);
        }
        $manager->flush();
    }
}
