<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(): Response { return $this->render('home.html.twig'); }

    #[Route('/healthz', name: 'healthz', methods: ['GET'])]
    public function health(): Response { return new Response('ok', 200, ['Content-Type' => 'text/plain']); }
}
