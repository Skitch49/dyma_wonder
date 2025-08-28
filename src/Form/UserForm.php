<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class UserForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', null, ['label' => '*Prénom'])
            ->add('lastname', null, ['label' => '*Nom'])
            ->add('email', null, ['label' => '*Email'])
            ->add('pictureFile', FileType::class, [
                'label' => '*Image',
                'mapped' => false,
                'constraints' => [
                    new Image(
                        mimeTypesMessage:'Veuillez soumettre une image !',
                        maxSize:'1M',
                        maxSizeMessage:'Votre image fait plus de {{ size }} {{ suffix }} or, la limite est de {{ limit }} {{ suffix }}.'
                    )
                ]
            ])
            ->add('password', PasswordType::class, ['label' => '*Mot de passe'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
