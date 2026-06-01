<?php

declare(strict_types=1);

namespace App\UI\Web\Form;

use App\UI\Web\Dto\LoginLinkWebInput;
use App\UI\Web\Form\EventSubscriber\TrimSubmittedStringsSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<LoginLinkWebInput>
 */
final class LoginLinkWebFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventSubscriber(new TrimSubmittedStringsSubscriber());

        $builder->add('email', EmailType::class, [
            'label' => 'form.email.label',
            'attr' => ['autocomplete' => 'email', 'placeholder' => 'form.email.placeholder', 'inputmode' => 'email'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'web_login_link',
            'data_class' => LoginLinkWebInput::class,
            'translation_domain' => 'messages',
        ]);
    }
}
