<?php

declare(strict_types=1);

namespace App\UI\Web\Form;

use App\Domain\Enum\EvmRpcPreset;
use App\UI\Web\Dto\Web3WalletCreateWebInput;
use App\UI\Web\Form\EventSubscriber\AutoFillRpcEndpointSubscriber;
use App\UI\Web\Form\EventSubscriber\TrimSubmittedStringsSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Web3WalletCreateWebInput>
 */
final class Web3WalletCreateWebFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventSubscriber(new TrimSubmittedStringsSubscriber());
        $builder->addEventSubscriber(new AutoFillRpcEndpointSubscriber());

        $choices = [];
        foreach (EvmRpcPreset::cases() as $preset) {
            $choices[$preset->networkName()] = $preset->value;
        }

        $builder
            ->add('address', TextType::class, [
                'label' => 'form.wallet.address.label',
                'attr' => ['placeholder' => 'form.wallet.address.placeholder', 'maxlength' => 42, 'spellcheck' => 'false', 'inputmode' => 'text'],
            ])
            ->add('rpcPreset', ChoiceType::class, [
                'label' => 'form.wallet.network.label',
                'choices' => $choices,
                'placeholder' => false,
                'choice_translation_domain' => false,
            ])
            ->add('rpcEndpoint', UrlType::class, [
                'label' => 'form.wallet.rpc_endpoint.label',
                'required' => false,
                'attr' => ['placeholder' => 'form.wallet.rpc_endpoint.placeholder'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'web_wallet_create',
            'data_class' => Web3WalletCreateWebInput::class,
            'translation_domain' => 'messages',
        ]);
    }
}
