<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201014084434 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE api_log (id INT AUTO_INCREMENT NOT NULL, enseignant_id INT NOT NULL, type VARCHAR(255) NOT NULL, called_at DATETIME NOT NULL, INDEX IDX_CCBD2EF1E455FCC0 (enseignant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE enseignant (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, preference_nb_elements_tableaux INT NOT NULL, UNIQUE INDEX UNIQ_81A72FA1E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE etudiant (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, mail VARCHAR(255) NOT NULL, est_demissionaire TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE etudiant_groupe_etudiant (etudiant_id INT NOT NULL, groupe_etudiant_id INT NOT NULL, INDEX IDX_B7A6D635DDEAB1A3 (etudiant_id), INDEX IDX_B7A6D6355237C26D (groupe_etudiant_id), PRIMARY KEY(etudiant_id, groupe_etudiant_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE evaluation (id INT AUTO_INCREMENT NOT NULL, enseignant_id INT DEFAULT NULL, groupe_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, date DATE NOT NULL, slug VARCHAR(255) NOT NULL, notes_saisies TINYINT(1) NOT NULL, INDEX IDX_1323A575E455FCC0 (enseignant_id), INDEX IDX_1323A5757A45358C (groupe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE groupe_etudiant (id INT AUTO_INCREMENT NOT NULL, enseignant_id INT NOT NULL, tree_root INT DEFAULT NULL, parent_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, lft INT NOT NULL, lvl INT NOT NULL, rgt INT NOT NULL, est_evaluable TINYINT(1) NOT NULL, slug VARCHAR(255) NOT NULL, INDEX IDX_E0DC2993E455FCC0 (enseignant_id), INDEX IDX_E0DC2993A977936C (tree_root), INDEX IDX_E0DC2993727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE partie (id INT AUTO_INCREMENT NOT NULL, evaluation_id INT DEFAULT NULL, tree_root INT DEFAULT NULL, parent_id INT DEFAULT NULL, intitule VARCHAR(255) DEFAULT NULL, bareme DOUBLE PRECISION DEFAULT NULL, lft INT NOT NULL, lvl INT NOT NULL, rgt INT NOT NULL, INDEX IDX_59B1F3D456C5646 (evaluation_id), INDEX IDX_59B1F3DA977936C (tree_root), INDEX IDX_59B1F3D727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE points (id INT AUTO_INCREMENT NOT NULL, etudiant_id INT DEFAULT NULL, partie_id INT DEFAULT NULL, valeur DOUBLE PRECISION NOT NULL, INDEX IDX_27BA8E29DDEAB1A3 (etudiant_id), INDEX IDX_27BA8E29E075F7A4 (partie_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE shortened_url (id INT AUTO_INCREMENT NOT NULL, url_token VARCHAR(255) NOT NULL, url_parameters JSON NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE statut (id INT AUTO_INCREMENT NOT NULL, enseignant_id INT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, slug VARCHAR(255) NOT NULL, INDEX IDX_E564F0BFE455FCC0 (enseignant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE statut_etudiant (statut_id INT NOT NULL, etudiant_id INT NOT NULL, INDEX IDX_E789E42F6203804 (statut_id), INDEX IDX_E789E42DDEAB1A3 (etudiant_id), PRIMARY KEY(statut_id, etudiant_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE api_log ADD CONSTRAINT FK_CCBD2EF1E455FCC0 FOREIGN KEY (enseignant_id) REFERENCES enseignant (id)');
        $this->addSql('ALTER TABLE etudiant_groupe_etudiant ADD CONSTRAINT FK_B7A6D635DDEAB1A3 FOREIGN KEY (etudiant_id) REFERENCES etudiant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE etudiant_groupe_etudiant ADD CONSTRAINT FK_B7A6D6355237C26D FOREIGN KEY (groupe_etudiant_id) REFERENCES groupe_etudiant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evaluation ADD CONSTRAINT FK_1323A575E455FCC0 FOREIGN KEY (enseignant_id) REFERENCES enseignant (id)');
        $this->addSql('ALTER TABLE evaluation ADD CONSTRAINT FK_1323A5757A45358C FOREIGN KEY (groupe_id) REFERENCES groupe_etudiant (id)');
        $this->addSql('ALTER TABLE groupe_etudiant ADD CONSTRAINT FK_E0DC2993E455FCC0 FOREIGN KEY (enseignant_id) REFERENCES enseignant (id)');
        $this->addSql('ALTER TABLE groupe_etudiant ADD CONSTRAINT FK_E0DC2993A977936C FOREIGN KEY (tree_root) REFERENCES groupe_etudiant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE groupe_etudiant ADD CONSTRAINT FK_E0DC2993727ACA70 FOREIGN KEY (parent_id) REFERENCES groupe_etudiant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3D456C5646 FOREIGN KEY (evaluation_id) REFERENCES evaluation (id)');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3DA977936C FOREIGN KEY (tree_root) REFERENCES partie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE partie ADD CONSTRAINT FK_59B1F3D727ACA70 FOREIGN KEY (parent_id) REFERENCES partie (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE points ADD CONSTRAINT FK_27BA8E29DDEAB1A3 FOREIGN KEY (etudiant_id) REFERENCES etudiant (id)');
        $this->addSql('ALTER TABLE points ADD CONSTRAINT FK_27BA8E29E075F7A4 FOREIGN KEY (partie_id) REFERENCES partie (id)');
        $this->addSql('ALTER TABLE statut ADD CONSTRAINT FK_E564F0BFE455FCC0 FOREIGN KEY (enseignant_id) REFERENCES enseignant (id)');
        $this->addSql('ALTER TABLE statut_etudiant ADD CONSTRAINT FK_E789E42F6203804 FOREIGN KEY (statut_id) REFERENCES statut (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE statut_etudiant ADD CONSTRAINT FK_E789E42DDEAB1A3 FOREIGN KEY (etudiant_id) REFERENCES etudiant (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE api_log DROP FOREIGN KEY FK_CCBD2EF1E455FCC0');
        $this->addSql('ALTER TABLE evaluation DROP FOREIGN KEY FK_1323A575E455FCC0');
        $this->addSql('ALTER TABLE groupe_etudiant DROP FOREIGN KEY FK_E0DC2993E455FCC0');
        $this->addSql('ALTER TABLE statut DROP FOREIGN KEY FK_E564F0BFE455FCC0');
        $this->addSql('ALTER TABLE etudiant_groupe_etudiant DROP FOREIGN KEY FK_B7A6D635DDEAB1A3');
        $this->addSql('ALTER TABLE points DROP FOREIGN KEY FK_27BA8E29DDEAB1A3');
        $this->addSql('ALTER TABLE statut_etudiant DROP FOREIGN KEY FK_E789E42DDEAB1A3');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3D456C5646');
        $this->addSql('ALTER TABLE etudiant_groupe_etudiant DROP FOREIGN KEY FK_B7A6D6355237C26D');
        $this->addSql('ALTER TABLE evaluation DROP FOREIGN KEY FK_1323A5757A45358C');
        $this->addSql('ALTER TABLE groupe_etudiant DROP FOREIGN KEY FK_E0DC2993A977936C');
        $this->addSql('ALTER TABLE groupe_etudiant DROP FOREIGN KEY FK_E0DC2993727ACA70');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3DA977936C');
        $this->addSql('ALTER TABLE partie DROP FOREIGN KEY FK_59B1F3D727ACA70');
        $this->addSql('ALTER TABLE points DROP FOREIGN KEY FK_27BA8E29E075F7A4');
        $this->addSql('ALTER TABLE statut_etudiant DROP FOREIGN KEY FK_E789E42F6203804');
        $this->addSql('DROP TABLE api_log');
        $this->addSql('DROP TABLE enseignant');
        $this->addSql('DROP TABLE etudiant');
        $this->addSql('DROP TABLE etudiant_groupe_etudiant');
        $this->addSql('DROP TABLE evaluation');
        $this->addSql('DROP TABLE groupe_etudiant');
        $this->addSql('DROP TABLE partie');
        $this->addSql('DROP TABLE points');
        $this->addSql('DROP TABLE shortened_url');
        $this->addSql('DROP TABLE statut');
        $this->addSql('DROP TABLE statut_etudiant');
    }
}
