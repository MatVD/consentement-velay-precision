<?php
namespace App\Form;

use App\Dto\ConsentSubmission;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConsentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'Prénom'])
            ->add('lastName', TextType::class, ['label' => 'Nom'])
            ->add('signatoryRole', TextType::class, ['label' => 'Qualité / fonction dans l’entreprise'])
            ->add('choice', ChoiceType::class, [
                'label' => 'Votre décision',
                'choices' => ['Oui, j’accepte' => 'yes', 'Non, je refuse' => 'no'],
                'expanded' => true,
                'multiple' => false,
                'placeholder' => false,
            ]);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ConsentSubmission::class, 'csrf_protection' => true, 'csrf_token_id' => 'velay_consent']);
    }
}
