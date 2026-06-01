<?php

declare(strict_types=1);

namespace App\UI\Web\Form;

use App\UI\Web\Dto\LoginPasswordWebInput;
use App\UI\Web\Form\EventSubscriber\TrimSubmittedStringsSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<LoginPasswordWebInput>
 */
final class LoginPasswordWebFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventSubscriber(new TrimSubmittedStringsSubscriber());

        $builder
            ->add('email', EmailType::class, [
                'label' => 'form.email.label',
                'attr' => ['autocomplete' => 'email', 'placeholder' => 'form.email.placeholder', 'inputmode' => 'email'],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'form.password.label',
                'attr' => ['autocomplete' => 'current-password', 'placeholder' => 'form.password.placeholder'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'web_password_login',
            'data_class' => LoginPasswordWebInput::class,
            'translation_domain' => 'messages',
        ]);
    }
}
