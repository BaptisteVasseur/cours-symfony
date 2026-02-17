<?php

namespace App\Controller;

use App\Entity\Property;
use App\Entity\User;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RegisterController extends AbstractController
{
    #[Route('/register', name: 'register')]
    public function register(Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer, UserPasswordHasherInterface $passwordHasher): Response
    {
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $firstName = $request->request->get('firstName');
        $lastName = $request->request->get('lastName');
        $phone = $request->request->get('phone');

        if ($request->isMethod('POST')) {
            $user = new User();
            $user->setEmail($email);
            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setPhone($phone);

            $hashedPassword = $passwordHasher->hashPassword($user, $password);
            $user->setPasswordHash($hashedPassword);
            $user->setActivationToken(hash('sha256', uniqid()));

            $entityManager->persist($user);

//            try {
                $entityManager->flush();

                $url = $this->generateUrl('account_confirmation', ['token' => $user->getActivationToken()], UrlGeneratorInterface::ABSOLUTE_URL);

                $emailToSend = new TemplatedEmail();
                $emailToSend
                    ->from('contact@airbnb.com')
                    ->to($email)
                    ->subject('Activation de votre compte Airbnb')
                    ->htmlTemplate('emails/activation.html.twig')
                    ->context([
                        'url' => $url,
                    ])
                ;
                $mailer->send($emailToSend);

//            } catch (\Throwable $e) {
//                $this->addFlash('error', 'An error occurred during registration. Please try again.');
//                return $this->render('security/register.html.twig');
//            }

            $this->addFlash('success', 'Inscription réussi, vous devez activer votre compte via le lien envoyé par email avant de pouvoir vous connecter.');
        }

       return $this->render('security/register.html.twig');
    }
}
