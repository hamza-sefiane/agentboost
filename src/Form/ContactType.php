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
                'label' => 'contact.form.name',
            ])

            ->add('email', EmailType::class, [
                'label' => 'contact.form.email',
            ])

            ->add('subject', ChoiceType::class, [
                'label' => 'contact.form.subject',

                'choices' => [
                    'contact.subject.subscription' => 'subscription',
                    'contact.subject.billing' => 'billing',
                    'contact.subject.bug' => 'bug',
                    'contact.subject.valuation' => 'valuation',
                    'contact.subject.ai' => 'ai',
                    'contact.subject.other' => 'other',
                ],

                'translation_domain' => 'messages',
            ])

            ->add('message', TextareaType::class, [
                'label' => 'contact.form.message',

                'attr' => [
                    'rows' => 8,
                ],
            ]);
    }
}
