<?php
namespace App\Controller;

use App\Entity\ConsentRequest;
use App\Form\ConsentType;
use App\Service\ConsentPolicy;
use App\Service\ConsentPrefill;
use App\Service\ConsentRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConsentController extends AbstractController
{
    #[Route('/c/{token}', name: 'app_consent', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET', 'POST'])]
    public function form(string $token, Request $request, EntityManagerInterface $em, ConsentRecorder $recorder): Response
    {
        $invitation = $em->getRepository(ConsentRequest::class)->findOneBy(['tokenHash' => hash('sha256', $token)]);
        if (!$invitation || !$invitation->isOpen() || !$invitation->getClient()->isActive() || in_array($invitation->getClient()->getStatus(), ['yes', 'no'], true)) {
            return $this->render('consent/invalid.html.twig', [], new Response(status: 410));
        }

        // Offer the client's recorded representative details without fixing the identity:
        // the recipient can verify and correct all three fields before confirming.
        $data = ConsentPrefill::forClient($invitation->getClient());
        $form = $this->createForm(ConsentType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $recorder->record($token, $data, $request);
                return $this->redirectToRoute('app_consent_thanks', [], Response::HTTP_SEE_OTHER);
            } catch (\DomainException) {
                return $this->render('consent/invalid.html.twig', [], new Response(status: 410));
            }
        }

        return $this->render('consent/form.html.twig', [
            'form' => $form,
            'client' => $invitation->getClient(),
            'policy' => ConsentPolicy::TEXT,
            'text_version' => ConsentPolicy::VERSION,
        ], new Response(headers: ['Cache-Control' => 'no-store']));
    }

    #[Route('/merci', name: 'app_consent_thanks', methods: ['GET'])]
    public function thanks(): Response
    {
        return $this->render('consent/thanks.html.twig', [], new Response(headers: ['Cache-Control' => 'no-store']));
    }
}
