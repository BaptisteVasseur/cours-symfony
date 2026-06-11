<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class ChehAccessSubscriber implements EventSubscriberInterface
{
    private const CHEH_ROUTE = 'app_cheh';
    private const ALLOWED_PATHS = ['/cheh', '/logout'];

    public function __construct(
        private Security $security,
        private RouterInterface $router,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user || !$this->security->isGranted('ROLE_CHEH')) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        foreach (self::ALLOWED_PATHS as $allowed) {
            if (str_starts_with($path, $allowed)) {
                return;
            }
        }

        $event->setResponse(new RedirectResponse($this->router->generate(self::CHEH_ROUTE)));
    }
}
