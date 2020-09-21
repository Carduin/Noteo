<?php

namespace App\Form;

use App\Entity\Etudiant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Translation\TranslatorInterface;

class GroupeEtudiantEditType extends AbstractType
{
    private $translator;

    public function __construct(TranslatorInterface $translator) {
        $this->translator = $translator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
      $builder
          ->add('nom', TextType::class)

          ->add('description', TextareaType::class, [
            'attr' => [
              'rows' => 6 //Pour limiter la hauteur initiale du champ de saisie à 6 lignes
            ]
          ])

          ->add('estEvaluable', ChoiceType::class, [
            'choices' => [$this->translator->trans('oui') => true, $this->translator->trans('non') => false],
            'data' => $options['estEvaluable'],
            'expanded' => true, // Pour avoir des boutons radio
            'label_attr' =>  [
            'class'=>'radio-inline' //Pour que les boutons radio soient alignés
            ]
          ])

          ->add('etudiantsAAjouter', EntityType::class, [
            'class' => Etudiant::Class, //On veut choisir des étudiants
            'choice_label' => false, // On n'affichera pas d'attribut de l'entité à côté du bouton pour aider au choix car on liste les entités nous même
            'label' => false,
            'mapped' => false, // Pour que l'attribut ne soit pas immédiatement mis en BD mais soit récupérable après validation
            'expanded' => true, // Pour avoir des cases
            'multiple' => true, // à cocher
            'choices' => $options['GroupeAjout'] // On restreint le choix à la liste des étudiants passée en parametre
          ])

          ->add('etudiantsASupprimer', EntityType::class, [
            'class' => Etudiant::Class,
            'choice_label' => false,
            'label' => false,
            'mapped' => false,
            'expanded' => true,
            'multiple' => true,
            'choices' => $builder->getData()->getEtudiants() //Onr restreint le choix à la liste du groupe pour lequel le formulaire est créé
          ])
      ;
    }

    //Cette fonction sert à donner des valeurs par défaut aux options passées lors de la création du formulaire
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'GroupeAjout' => null, //Si pas de groupe renseigné, affichera tous les étudiants de la base
            'estEvaluable' => false
        ]);
    }
}
