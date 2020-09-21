<?php

namespace App\Form;

use App\Entity\GroupeEtudiant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\File;

class GroupeEtudiantType extends AbstractType
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
                'rows' => 3
              ]
            ])
            ->add('estEvaluable', ChoiceType::class, [
              'choices' => [$this->translator->trans('oui') => true, $this->translator->trans('non') => false],
              'data' => false,
              'expanded' => true,
              'label_attr' =>  [
              'class'=>'radio-inline'
              ]
            ])

            ->add('fichier', FileType::class, [
              'mapped' => false,
              'constraints' => [new File([
                  'maxSize' => '16Mi',
                  'uploadFormSizeErrorMessage' => 'Le fichier ajouté est trop volumineux'
              ])],
              'attr' => [
                'placeholder' => 'Aucun fichier choisi',
                'accept' => '.csv'
              ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => GroupeEtudiant::class,
        ]);
    }
}
