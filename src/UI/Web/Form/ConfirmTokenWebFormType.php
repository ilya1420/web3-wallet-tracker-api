<?php

declare(strict_types=1);

namespace App\UI\Web\Form;

use App\UI\Web\Dto\ConfirmTokenWebInput;
use App\UI\Web\Form\EventSubscriber\TrimSubmittedStringsSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ConfirmTokenWebInput>
 */
final class ConfirmTokenWebFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventSubscriber(new TrimSubmittedStringsSubscriber());

        $builder->add('token', TextType::class, [
            'label' => 'form.confirm_token.label',
            'attr' => ['autocomplete' => 'one-time-code', 'placeholder' => 'form.confirm_token.placeholder'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'web_confirm_token',
            'data_class' => ConfirmTokenWebInput::class,
            'translation_domain' => 'messages',
        ]);
    }
}
