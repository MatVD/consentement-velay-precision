<?php
namespace App\Controller\Admin;

use App\Entity\Client;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->redirectToRoute('admin_client_index');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Velay Précision · Consentements');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToCrud('Clients', 'fa fa-users', Client::class);
        yield MenuItem::linkToRoute('Importer des clients', 'fa fa-file-import', 'admin_clients_import');
        yield MenuItem::linkToRoute('Exporter le CSV', 'fa fa-file-csv', 'admin_clients_export');
        yield MenuItem::linkToLogout('Déconnexion', 'fa fa-sign-out-alt');
    }
}
