<?php

declare(strict_types=1);

namespace App\UI\Web\Form;

use App\UI\Web\Dto\Web3WalletUpdateWebInput;
use App\UI\Web\Form\EventSubscriber\TrimSubmittedStringsSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Web3WalletUpdateWebInput>
 */
final class Web3WalletUpdateWebFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventSubscriber(new TrimSubmittedStringsSubscriber());

        $builder
            ->add('id', HiddenType::class)
            ->add('rpcEndpoint', UrlType::class, [
                'label' => 'form.wallet.rpc_endpoint.label',
                'attr' => ['placeholder' => 'form.wallet.rpc_endpoint.update_placeholder'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'web_wallet_update',
            'data_class' => Web3WalletUpdateWebInput::class,
            'translation_domain' => 'messages',
        ]);
    }
}
