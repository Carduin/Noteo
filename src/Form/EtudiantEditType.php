<?php

namespace App\Form;

use App\Entity\Etudiant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\NotNull;

class EtudiantEditType extends AbstractType
{
    private $translator;

    public function __construct(TranslatorInterface $translator) {
        $this->translator = $translator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {

      $builder
          ->add('nom')
          ->add('prenom')
          ->add('mail')
          ->add('estDemissionaire', ChoiceType::class, [
          'constraints' => [new NotNull],
              'choices' => [$this->translator->trans('oui') => true, $this->translator->trans('non') => false],
          'data' => $options['estDemissionaire'],
          'expanded' => true, // Pour avoir des boutons radio
          'label_attr' =>  [
          'class'=>'radio-inline' //Pour que les boutons radio soient alignés
          ]
          ])
      ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Etudiant::class,
            'estDemissionaire' => false
        ]);
    }
}
