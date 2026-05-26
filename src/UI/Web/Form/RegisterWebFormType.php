<?php

declare(strict_types=1);

namespace App\UI\Web\Form;

use App\UI\Web\Dto\RegisterWebInput;
use App\UI\Web\Form\EventSubscriber\TrimSubmittedStringsSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<RegisterWebInput>
 */
final class RegisterWebFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventSubscriber(new TrimSubmittedStringsSubscriber());

        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['autocomplete' => 'email', 'placeholder' => 'you@company.com', 'inputmode' => 'email'],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Password',
                'attr' => ['autocomplete' => 'new-password', 'minlength' => 8, 'placeholder' => 'StrongPass123!'],
            ])
            ->add('deviceFingerprint', HiddenType::class, [
                'attr' => ['data-device-fingerprint' => 'registration'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'web_register',
            'data_class' => RegisterWebInput::class,
        ]);
    }
}
