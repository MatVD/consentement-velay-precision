<?php
namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use function Symfony\Component\Translation\t;

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

    public function configureAssets(): Assets
    {
        return Assets::new()->addCssFile('assets/admin.css');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkTo(ClientCrudController::class, 'Clients', 'fa fa-users');
        yield MenuItem::linkToRoute('Importer des clients', 'fa fa-file-import', 'admin_clients_import');
        yield MenuItem::linkToRoute('Exporter le CSV', 'fa fa-file-csv', 'admin_clients_export');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        $items = [
            MenuItem::section(),
            MenuItem::linkToLogout('Déconnexion', 'internal:sign-out'),
        ];

        if ($this->isGranted(Permission::EA_EXIT_IMPERSONATION)) {
            $items[] = MenuItem::linkToExitImpersonation(t('user.exit_impersonation', domain: 'EasyAdminBundle'), 'internal:user-lock');
        }

        return parent::configureUserMenu($user)->setMenuItems($items);
    }
}
