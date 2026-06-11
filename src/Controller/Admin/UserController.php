<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\UserDocument;
use App\Entity\UserProfile;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Security\Roles;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    #[Route('', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        $users = $userRepository->findForListing();

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
            'total' => count($users),
            'active' => count(array_filter($users, static fn (User $u): bool => $u->getStatus() === 'active')),
            'pending' => count(array_filter($users, static fn (User $u): bool => $u->getStatus() === 'pending')),
            'suspended' => count(array_filter($users, static fn (User $u): bool => $u->getStatus() === 'suspended')),
            'roleLabels' => Roles::LABELS,
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        if ($user->getProfile() === null) {
            $user->setProfile(new UserProfile());
        }

        $form = $this->createForm(UserType::class, $user, [
            'is_creation' => true,
            'manage_elevated_roles' => $this->isGranted(Roles::SUPER_ADMIN),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur créé avec succès.');

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user, UserRepository $userRepository): Response
    {
        $user = $userRepository->findOneForDetail($user) ?? $user;

        return $this->render('admin/user/show.html.twig', [
            'user' => $user,
            'availableRoles' => Roles::ASSIGNABLE,
            'roleLabels' => Roles::LABELS,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($user->getProfile() === null) {
            $user->setProfile(new UserProfile());
        }

        $form = $this->createForm(UserType::class, $user, [
            'manage_elevated_roles' => $this->isGranted(Roles::SUPER_ADMIN),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur mis à jour avec succès.');

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');

            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur supprimé.');
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/document/{id}/verify', name: 'app_user_document_verify', methods: ['POST'])]
    public function verifyDocument(Request $request, UserDocument $document, EntityManagerInterface $entityManager): Response
    {
        $user = $document->getUser();
        if ($user === null || !$this->isCsrfTokenValid('document'.$document->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        $document->setVerificationStatus('verified');
        $this->syncIdentityStatus($user);
        $entityManager->flush();
        $this->addFlash('success', 'Document validé.');

        return $this->redirectToRoute('app_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/document/{id}/reject', name: 'app_user_document_reject', methods: ['POST'])]
    public function rejectDocument(Request $request, UserDocument $document, EntityManagerInterface $entityManager): Response
    {
        $user = $document->getUser();
        if ($user === null || !$this->isCsrfTokenValid('document'.$document->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        $document->setVerificationStatus('rejected');
        $profile = $user->getProfile();
        if ($profile !== null) {
            $profile->setIdentityStatus('rejected');
        }
        $entityManager->flush();
        $this->addFlash('success', 'Document refusé.');

        return $this->redirectToRoute('app_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/role/assign', name: 'app_user_role_assign', methods: ['POST'])]
    public function assignRole(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('role_assign'.$user->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        $roleCode = $request->getPayload()->getString('roleCode');
        if (!array_key_exists($roleCode, Roles::ASSIGNABLE)) {
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        if (in_array($roleCode, [Roles::ADMIN, Roles::SUPER_ADMIN], true) && !$this->isGranted(Roles::SUPER_ADMIN)) {
            $this->addFlash('error', 'Seul un super administrateur peut attribuer ce rôle.');

            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        if (!$user->hasAssignedRole($roleCode)) {
            $user->addAssignedRole($roleCode);
            $entityManager->flush();
            $this->addFlash('success', 'Rôle assigné.');
        }

        return $this->redirectToRoute('app_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/role/remove', name: 'app_user_role_remove', methods: ['POST'])]
    public function removeRole(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('role_remove'.$user->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        $roleCode = $request->getPayload()->getString('roleCode');
        if (array_key_exists($roleCode, Roles::ASSIGNABLE) && $user->hasAssignedRole($roleCode)) {
            $currentUser = $this->getUser();
            if ($currentUser instanceof User && $currentUser->getId() === $user->getId() && in_array($roleCode, [Roles::ADMIN, Roles::SUPER_ADMIN], true)) {
                $this->addFlash('error', 'Vous ne pouvez pas retirer votre propre rôle administrateur.');

                return $this->redirectToRoute('app_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
            }

            if (in_array($roleCode, [Roles::ADMIN, Roles::SUPER_ADMIN], true) && !$this->isGranted(Roles::SUPER_ADMIN)) {
                $this->addFlash('error', 'Seul un super administrateur peut retirer ce rôle.');

                return $this->redirectToRoute('app_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
            }

            $user->removeAssignedRole($roleCode);
            $entityManager->flush();
            $this->addFlash('success', 'Rôle retiré.');
        }

        return $this->redirectToRoute('app_user_show', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
    }

    private function syncIdentityStatus(User $user): void
    {
        $profile = $user->getProfile();
        if ($profile === null) {
            return;
        }

        $documents = $user->getDocuments();
        if ($documents->isEmpty()) {
            return;
        }

        foreach ($documents as $document) {
            if ($document->getVerificationStatus() === 'rejected') {
                $profile->setIdentityStatus('rejected');

                return;
            }
        }

        foreach ($documents as $document) {
            if ($document->getVerificationStatus() !== 'verified') {
                $profile->setIdentityStatus('pending');

                return;
            }
        }

        $profile->setIdentityStatus('verified');
    }
}
