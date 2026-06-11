<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TestController extends AbstractController
{
    #[Route('/test', name: 'app_test')]
    public function index(): Response
    {
        $users=[
            
        ];
        return $this->render('test/index.html.twig', [
            'users' => $users
        ]);
    }

    #[Route('/test2',name:"app_test2")]
    public function test2():Response
    {
        $message="Hello world !!!";
        return $this->render('test/test2.html.twig');
    }
}
