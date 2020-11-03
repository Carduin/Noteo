<?php

namespace App\Controller;

use App\Entity\Etudiant;
use App\Entity\GroupeEtudiant;
use App\Entity\Statut;
use App\Entity\enseignant;
use App\Form\StatutType;
use App\Form\EtudiantType;
use App\Form\EtudiantEditType;
use App\Repository\EtudiantRepository;
use App\Repository\GroupeEtudiantRepository;
use App\Repository\StatutRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("{_locale}/etudiant")
 */
class EtudiantController extends AbstractController
{
    /**
     * @Route("/", name="etudiant_index", methods={"GET"})
     */
    public function index(EtudiantRepository $etudiantRepository): Response
    {
        return $this->render('etudiant/index.html.twig', [
            'etudiants' => $etudiantRepository->findAllWithStatut(),
        ]);
    }

    /**
     * @Route("/nouveau", name="etudiant_new", methods={"GET","POST"})
     */
    public function new(Request $request, StatutRepository $repository): Response
    {
        $groupeRepository = $this->getDoctrine()->getRepository(GroupeEtudiant::class);
        $groupeEtudiantsNonAffectes = $groupeRepository->findOneBySlug('etudiants-non-affectes');
        $statuts = $repository->findAll();
        $etudiant = new Etudiant();
        $form = $this->createForm(EtudiantType::class, $etudiant);
        $form->handleRequest($request);
        $formStatut = $this->createFormBuilder()
            ->add('statuts', EntityType::class, [
                'class' => Statut::Class,
                'choice_label' => false,
                'label' => false,
                'mapped' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => $statuts
            ])
            ->getForm();
        $formStatut->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($formStatut->get('statuts')->getData() != null) {
                foreach ($formStatut->get('statuts')->getData() as $statut) {
                    $statut->addEtudiant($etudiant);
                }
            }
            $etudiant->addGroupe($groupeEtudiantsNonAffectes);
            $etudiant->setEstDemissionaire(false);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($etudiant);
            $entityManager->flush();
            return $this->redirectToRoute('etudiant_index');
        }

        return $this->render('etudiant/new.html.twig', [
            'etudiant' => $etudiant,
            'form' => $form->createView(),
            'formStatut' => $formStatut->createView(),
            'edit' => false
        ]);
    }

    /**
     * @Route("/consulter/{id}", name="etudiant_show", methods={"GET"})
     */
    public function show(Etudiant $etudiant, GroupeEtudiantRepository $repo): Response
    {
        $groupesDeLetudiant = $repo->findAllOrderedByStudent($etudiant);
        return $this->render('etudiant/show.html.twig', [
            'etudiant' => $etudiant,
            'groupes' => $groupesDeLetudiant,
        ]);
    }

    /**
     * @Route("/modifier/{id}", name="etudiant_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Etudiant $etudiant): Response
    {
        $estDemissionaire = $etudiant->getEstDemissionaire();
        $form = $this->createForm(EtudiantEditType::class, $etudiant, ['estDemissionaire' => $estDemissionaire]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('etudiant_show', [
                'id' => $etudiant->getId()
            ]);
        }
        return $this->render('etudiant/edit.html.twig', [
            'etudiant' => $etudiant,
            'form' => $form->createView(),
            'edit' => true
        ]);
    }

    /**
     * @Route("/supprimer/{id}", name="etudiant_delete", methods={"GET"})
     */
    public function delete(Etudiant $etudiant): Response
    {
        $manager = $this->getDoctrine()->getManager();
        foreach ($etudiant->getPoints() as $point) {
            $manager->remove($point);
        }
        foreach ($etudiant->getStatuts() as $statut) {
            $statut->removeEtudiant($etudiant);
        }
        $manager->remove($etudiant);
        $manager->flush();
        return $this->redirectToRoute('etudiant_index');
    }
}
