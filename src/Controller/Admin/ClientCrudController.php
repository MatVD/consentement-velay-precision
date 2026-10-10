<?php
namespace App\Controller\Admin;

use App\Entity\Client;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

final class ClientCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return Client::class; }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Client')
            ->setEntityLabelInPlural('Clients')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['name', 'email', 'codialId'])
            ->setPaginatorPageSize(25);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();
        yield TextField::new('name', 'Identité');
        yield EmailField::new('email', 'Email');
        yield TextField::new('codialId', 'Référence Codial')->hideOnIndex();
        yield ChoiceField::new('status', 'Statut')
            ->setChoices(['À faire' => Client::TODO, 'Mail envoyé' => Client::SENT, 'Oui' => Client::YES, 'Non' => Client::NO])
            ->renderAsBadges([Client::TODO => 'secondary', Client::SENT => 'info', Client::YES => 'success', Client::NO => 'danger'])
            ->hideOnForm();
        yield DateTimeField::new('statusAt', 'Date')->hideOnForm()->setFormat('dd/MM/yyyy HH:mm');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(ChoiceFilter::new('status')->setChoices([
            'À faire' => Client::TODO, 'Mail envoyé' => Client::SENT,
            'Oui' => Client::YES, 'Non' => Client::NO,
        ]));
    }

    public function configureActions(Actions $actions): Actions
    {
        $follow = Action::new('suivi', 'Formulaire / preuves', 'fa fa-file-signature')
            ->linkToRoute('admin_client_actions', static fn (Client $client): array => ['id' => $client->getId()]);
        return $actions
            ->add(Crud::PAGE_INDEX, $follow)
            ->add(Crud::PAGE_DETAIL, $follow)
            ->disable(Action::DELETE);
    }
}
