<?php

declare(strict_types=1);

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HelpController extends AbstractController
{
    #[Route('/aide', name: 'app_help', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('front/help/index.html.twig');
    }
}
