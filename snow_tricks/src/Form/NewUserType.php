<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NewUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'invalid_message' => "Le nom d'utilisateur ne peut pas dépasser 45 caractères.",
                'attr' => [
                    'maxlength' => 45,
                    'placeholder' => "Saisissez un nom d'utilisateur unique",
                ]
            ])

            ->add('email', EmailType::class, [
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => "Adresse@domaine.com"
                ]
            ])

            ->add('password', PasswordType::class, [
                'attr' => [
                    'placeholder' => "Saisissez un mot de passe"
                ]
            ])

            ->add('roles', ChoiceType::class, [
                'multiple' => false,
                'expanded' => false,
                'choices'  => [
                    'Utilisateur'    => 'ROLE_USER',
                    'Modérateur'     => 'ROLE_MODO',
                    'Administrateur' => 'ROLE_ADMIN',
                ]
            ])
        ;

        // Data transformer
        $builder->get('roles')
            ->addModelTransformer(new CallbackTransformer(
                function ($rolesArray) {
                    // transform the array to a string
                    return count($rolesArray)? $rolesArray[0]: null;
                },
                function ($rolesString) {
                    // transform the string back to an array
                    return [$rolesString];
                }
            ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
