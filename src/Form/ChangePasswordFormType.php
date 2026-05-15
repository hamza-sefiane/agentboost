<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les deux mots de passe doivent être identiques.',
                'options' => [
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'class' => 'auth-input',
                    ],
                    'row_attr' => [
                        'class' => 'auth-field',
                    ],
                ],
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'constraints' => [
                        new NotBlank(
                            message: 'Veuillez saisir un mot de passe.',
                        ),
                        new Length(
                            min: 12,
                            minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.',
                            max: 4096,
                        ),
                        new PasswordStrength(
                            minScore: PasswordStrength::STRENGTH_MEDIUM,
                            message: 'Votre mot de passe est trop faible.',
                        ),
                        new NotCompromisedPassword(
                            message: 'Ce mot de passe apparaît dans une fuite de données. Choisissez-en un autre.',
                        ),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}