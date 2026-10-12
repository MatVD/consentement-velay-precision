<?php
namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE)]
final class SensitiveResponseHeadersListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // The bearer token embedded in the public URL must never be forwarded
        // as a Referer to other origins, including via future external assets.
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        $path = $request->getPathInfo();
        if (str_starts_with($path, '/admin')
            || str_starts_with($path, '/c/')
            || in_array($path, ['/login', '/logout', '/merci'], true)
        ) {
            // Applies to success, errors, redirects and invalid/expired tokens.
            $response->headers->set('Cache-Control', 'private, no-store');
        }
    }
}
