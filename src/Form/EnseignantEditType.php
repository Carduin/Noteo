<?php

namespace App\Form;

use App\Entity\Enseignant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\NotNull;

class EnseignantEditType extends AbstractType
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
        ->add('email')
        ->add('estAdmin', ChoiceType::class, [
          'constraints' => [new NotNull],
          'help' => '$this->translator->trans(\'form_enseignant_message_info_droits\')',
          'choices' => [$this->translator->trans('oui') => true, $this->translator->trans('non') => false],
          'data' => $options['estAdmin'],
          'mapped' => false,
          'disabled' => $options['champDesactive'],
          'expanded' => true, // Pour avoir des boutons radio
          'label_attr' =>  [
            'class'=>'radio-inline' //Pour que les boutons radio soient alignés
          ]
        ])
        ->add('preferenceNbElementsTableaux', ChoiceType::class, [
            'choices' => [
                '15' => 15,
                '30' => 30,
                '45' => 45,
                $this->translator->trans('tous_les_elements') => -1
            ],
            'label' => $this->translator->trans('form_enseignant_preference_tableau')
        ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Enseignant::class,
            'champDesactive' => false,
            'estAdmin' => false
        ]);
    }
}
