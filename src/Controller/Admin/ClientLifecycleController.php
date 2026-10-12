<?php
namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\ClientLifecycleEvent;
use App\Service\ClientLifecycleService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ClientLifecycleController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[AdminRoute(path: '/clients/{id}/close-contract', name: 'client_close_contract',
        options: ['requirements' => ['id' => '\\d+'], 'methods' => ['POST']])]
    public function closeContract(Client $client, Request $request, ClientLifecycleService $lifecycle): Response
    {
        if (!$this->isCsrfTokenValid('archive_'.$client->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $raw = (string) $request->request->get('ended_at');
        $localZone = new \DateTimeZone('Europe/Paris');
        $local = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $localZone);
        if (!$local || $local->format('Y-m-d') !== $raw) {
            $this->addFlash('danger', 'Indiquez une date de fin de contrat valide.');
        } else {
            try {
                $lifecycle->archive($client, $local->setTimezone(new \DateTimeZone('UTC')));
                $this->addFlash('success', 'Client archivé. Les justificatifs sont conservés pour le moment.');
            } catch (\DomainException $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }
        }

        return $this->redirectToRoute('admin_client_actions', ['id' => $client->getId()], Response::HTTP_SEE_OTHER);
    }

    #[IsGranted('ROLE_AUDITOR')]
    #[AdminRoute(path: '/lifecycle/history', name: 'lifecycle_history', options: ['methods' => ['GET']])]
    public function history(EntityManagerInterface $em): Response
    {
        return $this->render('admin/lifecycle_history.html.twig', [
            'events' => $em->getRepository(ClientLifecycleEvent::class)->findBy([], ['id' => 'DESC'], 100),
        ], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }
}
