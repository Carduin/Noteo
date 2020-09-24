<?php

namespace App\Controller;

use App\Entity\Statut;
use App\Form\StatutType;
use App\Form\StatutEditType;
use App\Repository\StatutRepository;
use App\Repository\EtudiantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("{_locale}/statut")
 */
class StatutController extends AbstractController
{
    /**
     * @Route("/", name="statut_index", methods={"GET"})
     */
    public function index(StatutRepository $statutRepository): Response
    {
        return $this->render('statut/index.html.twig', [
            'statuts' => $statutRepository->findAllWithStudents(),
        ]);
    }

    /**
     * @Route("/nouveau", name="statut_new", methods={"GET","POST"})
     */
    public function new(Request $request, EtudiantRepository $etudiantRepository): Response
    {
        $statut = new Statut();
        $form = $this->createForm(StatutType::class, $statut, ['etudiants' => $etudiantRepository->findAll()]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $statut->setEnseignant($this->getUser());
            if ($statut->getEtudiants() != null) {
                foreach ($form->get('lesEtudiantsAAjouter')->getData() as $key => $etudiant) {
                    $statut->addEtudiant($etudiant);
                }
            }
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($statut);
            $entityManager->flush();
            return $this->redirectToRoute('statut_index');
        }
        return $this->render('statut/new.html.twig', [
            'statut' => $statut,
            'etudiants' => $etudiantRepository->findAll(),
            'form' => $form->createView(),
            'edit' => false
        ]);
    }

    /**
     * @Route("/consulter/{slug}", name="statut_show", methods={"GET"})
     */
    public function show(Statut $statut): Response
    {
        return $this->render('statut/show.html.twig', [
            'statut' => $statut,
            'etudiants' => $statut->getEtudiants(),
        ]);
    }

    /**
     * @Route("/modifier/{slug}", name="statut_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Statut $statut, EtudiantRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('STATUT_EDIT', $statut);
        //On récupère tous les étudiants que l'on pourra ajouter au statut c'est à dire tous sauf ceux qui l'ont déjà
        $form = $this->createForm(StatutEditType::class, $statut, ['etudiantsAjout' => $repo->findAllButNotFromCurrentStatuts($statut)]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($form->get('lesEtudiantsAAjouter')->getData() as $key => $etudiant) {
                $etudiant->addStatut($statut);
            }
            foreach ($form->get('lesEtudiantsASupprimer')->getData() as $key => $etudiant) {
                $etudiant->removeStatut($statut);
            }
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('statut_show', [
                'slug' => $statut->getSlug()
            ]);
        }
        return $this->render('statut/edit.html.twig', [
            'statut' => $statut,
            'form' => $form->createView(),
            'edit' => true,
        ]);
    }

    /**
     * @Route("/supprimer/{slug}", name="statut_delete", methods={"GET"})
     */
    public function delete(Request $request, Statut $statut): Response
    {
        $this->denyAccessUnlessGranted('STATUT_EDIT', $statut);
        foreach ($statut->getEtudiants() as $key => $etudiant) {
            $statut->removeEtudiant($etudiant);
        }
        $manager = $this->getDoctrine()->getManager();
        $manager->remove($statut);
        $manager->flush();
        return $this->redirectToRoute('statut_index');
    }

}
