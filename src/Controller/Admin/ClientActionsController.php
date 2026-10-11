<?php
namespace App\Controller\Admin;

use App\Entity\AuditEvent;
use App\Entity\Client;
use App\Entity\ConsentDecision;
use App\Entity\EvidenceFile;
use App\Form\ConsentType;
use App\Service\ConsentPolicy;
use App\Service\ConsentPrefill;
use App\Service\ConsentRecorder;
use App\Service\EvidenceArchive;
use App\Service\InvitationService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class ClientActionsController extends AbstractController
{
    #[AdminRoute(path: '/clients/{id}/actions', name: 'client_actions', options: ['requirements' => ['id' => '\\d+'], 'methods' => ['GET']])]
    public function actions(Client $client, EntityManagerInterface $em): Response
    {
        $files = $em->createQueryBuilder()
            ->select('e')->from(EvidenceFile::class, 'e')
            ->join('e.decision', 'd')->where('d.client = :client')
            ->setParameter('client', $client)->getQuery()->getResult();
        return $this->render('admin/client_actions.html.twig', [
            'client' => $client,
            'decisions' => $em->getRepository(ConsentDecision::class)->findBy(['client' => $client], ['id' => 'DESC']),
            'events' => $em->getRepository(AuditEvent::class)->findBy(['client' => $client], ['id' => 'DESC'], 30),
            'files' => $files,
        ]);
    }

    #[Route('/admin/clients/{id}/email', name: 'admin_client_send_email', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function sendEmail(Client $client, Request $request, InvitationService $inviter): Response
    {
        if (!$this->isCsrfTokenValid('send_'.$client->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        try {
            $inviter->sendEmail($client);
            $this->addFlash('success', 'Email envoyé.');
        } catch (\Throwable) {
            $this->addFlash('danger', 'Envoi impossible : vérifier le client, le SMTP et les journaux serveur.');
        }
        return $this->redirectToRoute('admin_client_index');
    }

    #[Route('/admin/clients/{id}/onsite', name: 'admin_client_onsite', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function onsite(Client $client, Request $request, InvitationService $inviter): Response
    {
        if (!$this->isCsrfTokenValid('onsite_'.$client->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        try {
            [, $link] = $inviter->create($client, 'onsite');
            $this->addFlash('onsite_link', $link);
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
        return $this->redirectToRoute('admin_client_index');
    }

    #[AdminRoute(path: '/clients/{id}/assisted', name: 'client_assisted', options: ['requirements' => ['id' => '\\d+'], 'methods' => ['GET', 'POST']])]
    public function assisted(Client $client, Request $request, ConsentRecorder $recorder): Response
    {
        if (in_array($client->getStatus(), [Client::YES, Client::NO], true)) {
            $this->addFlash('warning', 'Une décision définitive existe déjà pour ce client.');
            return $this->redirectToRoute('admin_client_index');
        }

        $data = ConsentPrefill::forClient($client);
        $form = $this->createForm(ConsentType::class, $data, ['assisted' => true]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $operator = $this->getUser();
            if ($operator === null) {
                throw $this->createAccessDeniedException('Authentification requise.');
            }
            try {
                $recorder->recordAssisted($client, $data, $request, $operator->getUserIdentifier());
                $this->addFlash('success', 'Décision enregistrée et justificatif créé.');
                return $this->redirectToRoute('admin_client_index', [], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $exception) {
                $this->addFlash('warning', $exception->getMessage());
                return $this->redirectToRoute('admin_client_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('admin/client_assisted.html.twig', [
            'client' => $client,
            'form' => $form,
            'policy' => ConsentPolicy::TEXT,
            'text_version' => ConsentPolicy::VERSION,
        ], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/admin/proofs/{id}/download', name: 'admin_proof_download', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function download(EvidenceFile $file, EvidenceArchive $archive): Response
    {
        $path = $archive->absolutePath($file);
        if (!is_file($path) || !hash_equals($file->getSha256(), (string) hash_file('sha256', $path))) {
            throw $this->createNotFoundException('Justificatif absent ou altéré.');
        }
        $response = new BinaryFileResponse($path);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'decision-'.$file->getDecision()->getId().'.pdf');
        return $response;
    }
}
