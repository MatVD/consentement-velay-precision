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
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
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
            ->setPaginatorPageSize(25)
            ->setPageTitle(Crud::PAGE_NEW, 'Ajouter un client')
            ->setPageTitle(Crud::PAGE_EDIT, 'Modifier le client')
            ->overrideTemplate('crud/index', 'admin/client_index.html.twig');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();
        if (in_array($pageName, [Crud::PAGE_NEW, Crud::PAGE_EDIT], true)) {
            yield FormField::addFieldset('Informations du client');
        }
        yield TextField::new('name', 'Entreprise / client')
            ->setHelp('Raison sociale ou nom du client, sans déduire l’identité de son représentant.');
        yield EmailField::new('email', 'Email d’invitation')->setRequired(false);
        yield TextField::new('codialId', 'Référence Codial')->hideOnIndex()->setRequired(false);
        if (in_array($pageName, [Crud::PAGE_NEW, Crud::PAGE_EDIT], true)) {
            yield FormField::addFieldset('Représentant (facultatif)')
                ->setHelp('Ces informations servent uniquement à proposer des valeurs modifiables au technicien.');
        }
        yield TextField::new('contactFirstName', 'Prénom du représentant')
            ->hideOnIndex()->setRequired(false)
            ->setHelp('Facultatif : uniquement si le prénom du représentant est connu.');
        yield TextField::new('contactLastName', 'Nom du représentant')->hideOnIndex()->setRequired(false);
        yield TextField::new('contactRole', 'Fonction du représentant')->hideOnIndex()->setRequired(false);
        yield ChoiceField::new('status', 'Statut')
            ->setChoices(['À faire' => Client::TODO, 'Mail envoyé' => Client::SENT, 'Oui' => Client::YES, 'Non' => Client::NO])
            ->renderAsBadges([Client::TODO => 'secondary', Client::SENT => 'info', Client::YES => 'success', Client::NO => 'danger'])
            ->hideOnForm();
        yield DateTimeField::new('statusAt', 'Date')->hideOnForm()->setFormat('dd/MM/yyyy HH:mm');
        yield Field::new('quickActions', 'Actions rapides')
            ->setVirtual(true)
            ->setSortable(false)
            ->setTemplatePath('admin/client_quick_actions.html.twig')
            ->onlyOnIndex();
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
        $follow = Action::new('suivi', 'Suivi / preuves', 'fa fa-file-signature')
            ->linkToRoute('admin_client_actions', static fn (Client $client): array => ['id' => $client->getId()]);
        return $actions
            ->add(Crud::PAGE_INDEX, $follow)
            ->add(Crud::PAGE_DETAIL, $follow)
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action): Action => $action->setLabel('Ajouter un client'))
            ->disable(Action::DELETE);
    }
}
