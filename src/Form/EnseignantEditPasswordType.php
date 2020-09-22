<?php

namespace App\Form;

use App\Entity\Enseignant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Translation\TranslatorInterface;

class EnseignantEditPasswordType extends AbstractType
{
    private $translator;

    public function __construct(TranslatorInterface $translator) {
        $this->translator = $translator;
        //$this->translator->trans('oui')
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
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
        ]);
    }
}
