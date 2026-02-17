<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\ResetPassword;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ResetPasswordController extends AbstractController
{
    #[Route('/reset-password', name: 'app_reset_password')]
    public function index(Request $request, ResetPassword $resetPassword): Response
    {
        $email = $request->request->get('email');

        if ($request->isMethod('POST')) {
            try {
                $resetPassword->makeAPasswordRequest($email);
            } catch (Exception) {
                $this->addFlash('error', 'Aucun compte trouvé avec cet email.');
                return $this->redirectToRoute('app_reset_password');
            }
        }

        return $this->render('security/reset-password/request.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_resetpassword_change')]
    public function change(string $token): Response
    {
        // Ici, vous devriez vérifier le token et la date d'expiration avant de permettre à l'utilisateur de changer son mot de passe.

        return $this->render('security/reset-password/change.html.twig');
    }
}
