<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Property;
use App\Entity\User;
use App\Form\HostPropertyType;
use App\Security\Roles;
use App\Security\Voter\PropertyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/proprietes')]
#[IsGranted('ROLE_USER')]
final class HostPropertyController extends AbstractController
{
    #[Route('/nouvelle', name: 'app_host_property_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $property = new Property();
        $property->setHost($user);
        $property->setStatus('published');

        $form = $this->createForm(HostPropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $becameHost = !$user->hasAssignedRole(Roles::HOST);

            if ($becameHost) {
                $user->addAssignedRole(Roles::HOST);
            }

            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->persist($property);
            $entityManager->flush();

            if ($becameHost) {
                $tokenStorage->getToken()?->setUser($user);
            }

            $this->addFlash(
                'success',
                $becameHost
                    ? 'Votre annonce est en ligne. Vous êtes maintenant hôte !'
                    : 'Votre annonce est en ligne.',
            );

            return $this->redirectToRoute('app_account_properties');
        }

        return $this->render('front/account/property_form.html.twig', [
            'form' => $form,
            'property' => null,
            'pageTitle' => 'Publier une annonce',
            'pageDescription' => 'Renseignez les informations de votre logement pour le mettre en location.',
            'submitLabel' => 'Publier l\'annonce',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_host_property_edit', methods: ['GET', 'POST'])]
    #[IsGranted(PropertyVoter::EDIT, subject: 'property')]
    public function edit(
        Request $request,
        Property $property,
        EntityManagerInterface $entityManager,
    ): Response {
        $form = $this->createForm(HostPropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $property->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Annonce mise à jour.');

            return $this->redirectToRoute('app_account_properties');
        }

        return $this->render('front/account/property_form.html.twig', [
            'form' => $form,
            'property' => $property,
            'pageTitle' => 'Modifier l\'annonce',
            'pageDescription' => 'Mettez à jour les informations de votre logement.',
            'submitLabel' => 'Enregistrer',
        ]);
    }
}
