<?php

namespace App\Controller;

use App\Entity\Enseignant;
use App\Form\EnseignantType;
use App\Form\EnseignantEditType;
use App\Form\EnseignantEditPasswordType;
use App\Repository\EnseignantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @Route("{_locale}/enseignant")
 */
class EnseignantController extends AbstractController
{
    /**
     * @Route("/", name="enseignant_index", methods={"GET"})
     */
    public function index(EnseignantRepository $enseignantRepository): Response
    {
        $this->denyAccessUnlessGranted('ENSEIGNANT_INDEX', new Enseignant());
        return $this->render('enseignant/index.html.twig', [
            'enseignants' => $enseignantRepository->findAll(),
        ]);
    }

    /**
     * @Route("/nouveau", name="enseignant_new", methods={"GET","POST"})
     */
    public function new(Request $request, ObjectManager $manager, UserPasswordEncoderInterface $encoder): Response
    {
        $this->denyAccessUnlessGranted('ENSEIGNANT_NEW', new Enseignant());
        $enseignant = new Enseignant();
        $enseignant->setPreferenceNbElementsTableaux(15); // Préférence de tri par défaut
        $form = $this->createForm(EnseignantType::class, $enseignant);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Si Oui au bouton radio
            if ($form['estAdmin']->getData()) {
                $enseignant->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
            } else {
                $enseignant->setRoles(['ROLE_USER']);
            }
            $mdpEncode = $encoder->encodePassword($enseignant, $enseignant->getPassword());
            $enseignant->setPassword($mdpEncode);
            $manager->persist($enseignant);
            $manager->flush();
            return $this->redirectToRoute('enseignant_index');
        }

        return $this->render('enseignant/new.html.twig', [
            'enseignant' => $enseignant,
            'form' => $form->createView(),
            'edit' => false
        ]);
    }

    /**
     * @Route("/consulter/{id}", name="enseignant_show", methods={"GET"})
     */
    public function show(Enseignant $enseignant): Response
    {
        $this->denyAccessUnlessGranted('ENSEIGNANT_SHOW', $enseignant);
        return $this->render('enseignant/show.html.twig', [
            'enseignant' => $enseignant,
        ]);
    }

    /**
     * @Route("/modifier/{id}", name="enseignant_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Enseignant $enseignant, UserPasswordEncoderInterface $encoder): Response
    {
        $this->denyAccessUnlessGranted('ENSEIGNANT_EDIT', $enseignant);
        // On verifie le rôle de l'utilisateur pour désactiver ou non les boutons radios permettant de définir le rôle
        $champDesactive = !$this->getUser()->isAdmin();
        // Utiliser pour définir le choix du bouton radio lors de l'édition
        $estAdmin = $enseignant->isAdmin();
        $form = $this->createForm(EnseignantEditType::class, $enseignant, ['champDesactive' => $champDesactive, 'estAdmin' => $estAdmin]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Si Oui au bouton radio
            if ($form['estAdmin']->getData()) {
                $enseignant->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
            } else {
                $enseignant->setRoles(['ROLE_USER']);
            }
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('enseignant_show', [
                'id' => $enseignant->getId()
            ]);
        }
        return $this->render('enseignant/edit.html.twig', [
            'enseignant' => $enseignant,
            'form' => $form->createView(),
            'edit' => true
        ]);
    }

    /**
     * @Route("/modifier-mot-de-passe/{id}", name="enseignant_edit_password", methods={"GET","POST"})
     */
    public function editPassword(Request $request, Enseignant $enseignant, UserPasswordEncoderInterface $encoder): Response
    {
        $this->denyAccessUnlessGranted('ENSEIGNANT_EDIT', $enseignant);
        $form = $this->createForm(EnseignantEditPasswordType::class, $enseignant);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $mdpEncode = $encoder->encodePassword($enseignant, $enseignant->getPassword());
            $enseignant->setPassword($mdpEncode);
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('enseignant_show', [
                'id' => $enseignant->getId()
            ]);
        }

        return $this->render('enseignant/edit_password.html.twig', [
            'enseignant' => $enseignant,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/supprimer/{id}", name="enseignant_delete", methods={"GET","POST"})
     */
    public function delete(Request $request, Enseignant $enseignant): Response
    {
        $this->denyAccessUnlessGranted('ENSEIGNANT_DELETE', $enseignant);
        //Pour qu'un administrateur ne supprime pas son propre profil
        if ($this->getUser()->getId() != $enseignant->getId()) {
            foreach ($enseignant->getStatuts() as $statut) {
                //La méthode forward permet d'éxécuter l'action métier d'un controlleur donné mais ne redirige pas l'utilisateur,
                // ce qui permet de ne pas dupliquer le code de la suppression dans ce cas
                $this->forward('App\Controller\StatutController::delete', [
                    'id' => $statut->getId(),
                ]);
            }
            foreach ($enseignant->getGroupes() as $groupe) {
                $this->forward('App\Controller\GroupeEtudiantController::delete', [
                    'id' => $groupe->getId(),
                ]);
            }
            foreach ($enseignant->getEvaluations() as $evaluation) {
                $this->forward('App\Controller\EvaluationController::delete', [
                    'id' => $evaluation->getId(),
                ]);
            }
        }
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($enseignant);
        $entityManager->flush();
        return $this->redirectToRoute('enseignant_index');
    }
}
