<?php

namespace App\Form;

use App\Entity\Enseignant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\NotNull;

class EnseignantType extends AbstractType
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
      'choices' => [$this->translator->trans('oui') => true, $this->translator->trans('non') => false],'data' => $options['estAdmin'],
      'mapped' => false,
      'disabled' => $options['champDesactive'],
      'expanded' => true, // Pour avoir des boutons radio
      'label_attr' =>  [
        'class'=>'radio-inline' //Pour que les boutons radio soient alignés
      ]
    ])
    ->add('password',RepeatedType::class, [
      'type' => PasswordType::class,
      'invalid_message' => $this->translator->trans('form_enseignant_mdp_invalide'),
      'options' => ['attr' => ['class' => 'password-field']],
      'required' => true,
      'first_options'  => ['label' => $this->translator->trans('form_enseignant_placeholder_mpd_1')],
      'second_options' => ['label' => $this->translator->trans('form_enseignant_placeholder_mpd_2')],
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
