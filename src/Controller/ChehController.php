<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CHEH')]
class ChehController extends AbstractController
{
    #[Route('/cheh', name: 'app_cheh')]
    public function index(): Response
    {
        return $this->render('cheh/index.html.twig');
    }
}
