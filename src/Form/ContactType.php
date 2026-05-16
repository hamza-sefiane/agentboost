<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;



final class ContactType extends AbstractType
{
   public function buildForm(FormBuilderInterface $builder, array $options): void
{
    $builder
        ->add('name', TextType::class, [
            'label' => 'Nom',
        ])

        ->add('email', EmailType::class, [
            'label' => 'Email',
        ])

        ->add('subject', ChoiceType::class, [
            'label' => 'Sujet',
            'choices' => [
                'Abonnement' => 'Abonnement',
                'Facturation' => 'Facturation',
                'Bug technique' => 'Bug technique',
                'Estimation immobilière' => 'Estimation immobilière',
                'Fonctionnalité IA' => 'Fonctionnalité IA',
                'Autre' => 'Autre',
            ],
        ])

        ->add('message', TextareaType::class, [
            'label' => 'Message',
            'attr' => [
                'rows' => 8,
            ],
        ]);
}
}
