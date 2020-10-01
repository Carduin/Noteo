<?php

namespace App\Command;

use App\Entity\Enseignant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateBaseAdminCommand extends Command
{
    private $entityManager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entityManager = $em;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName("app:create-base-admin")
            ->setDescription("This command allows you to create the first admin account when installing the app")
        ;

    }

    protected function execute(InputInterface $input, OutputInterface $output )
    {
        $io = new SymfonyStyle($input, $output);
        $io->newLine();
        $io->text('Création d\'un administrateur par défaut...');
        $io->progressStart(100);

        $baseAdmin = new Enseignant();
        $baseAdmin->setNom('Noteo');
        $baseAdmin->setPrenom('Admin');
        $baseAdmin->setEmail('noteo@admin.fr');
        $baseAdmin->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $baseAdmin->setPassword('$2y$10$DzfLbLyjpDUiLyYlPcw.L.RqtdOxxS7XCuXeg.bq3Glu5gG9W04WO');
        $baseAdmin->setPreferenceNbElementsTableaux(15);
        $baseAdmin->setToken(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
        $this->entityManager->persist($baseAdmin);
        $this->entityManager->flush();
        $io->progressFinish();
        $io->writeln([
            'Création du compte effectuée ! Identifiants :',
            'noteo@admin.fr',
            'noteo_admin'
        ]);
        $io->warning('Pensez à changer ces identifiants ou créer un nouveau compte le plus vite possible');

    }
}
