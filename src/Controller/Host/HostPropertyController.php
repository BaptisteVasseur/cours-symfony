<?php

declare(strict_types=1);

namespace App\Controller\Host;

use App\Entity\Property;
use App\Entity\PropertyAddress;
use App\Entity\PropertyMedia;
use App\Form\BecomeHostType;
use App\Form\HostPropertyType;
use App\Repository\PropertyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class HostPropertyController extends AbstractController
{
    #[Route('/host/mes-proprietes', name: 'host_my_properties', methods: ['GET'])]
    public function index(PropertyRepository $propertyRepository): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $properties = $this->isGranted('ROLE_HOST')
            ? $propertyRepository->findByHost($user)
            : [];

        $byCountry = [];
        foreach ($properties as $property) {
            $country = $property->getAddress()?->getCountry() ?? 'Autre';
            $byCountry[$country][] = $property;
        }
        ksort($byCountry);

        return $this->render('host/my_properties.html.twig', [
            'propertiesByCountry' => $byCountry,
            'isHost'              => $this->isGranted('ROLE_HOST'),
        ]);
    }

    #[Route('/devenir-hote', name: 'become_host', methods: ['GET', 'POST'])]
    public function becomeHost(Request $request, EntityManagerInterface $em): Response
    {
        // Already a host → use the dedicated new-property route
        if ($this->isGranted('ROLE_HOST')) {
            return $this->redirectToRoute('host_new_property');
        }

        return $this->handlePropertyForm($request, $em, promoteToHost: true);
    }

    #[Route('/host/nouveau-logement', name: 'host_new_property', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_HOST')]
    public function newProperty(Request $request, EntityManagerInterface $em): Response
    {
        return $this->handlePropertyForm($request, $em, promoteToHost: false);
    }

    private function handlePropertyForm(Request $request, EntityManagerInterface $em, bool $promoteToHost): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $property = new Property();
        $property->setHost($user);
        $property->setStatus('pending');

        $formClass = $promoteToHost ? BecomeHostType::class : HostPropertyType::class;
        $form = $this->createForm($formClass, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($promoteToHost) {
                $user->addAssignedRole('ROLE_HOST');
            }
            $property->setUpdatedAt(new \DateTimeImmutable());

            $address = new PropertyAddress();
            $address->setCity($form->get('city')->getData());
            $address->setCountry($form->get('country')->getData() ?: 'France');
            $address->setAddressLine1($form->get('addressLine1')->getData());
            $property->setAddress($address);

            $em->persist($property);
            $em->persist($address);

            // Photos (imageUrls uniquement pour HostPropertyType)
            if ($form->has('imageUrls')) {
                $urls = array_values(array_filter(
                    (array) $form->get('imageUrls')->getData(),
                    static fn (?string $u) => $u !== null && $u !== '',
                ));
                foreach ($urls as $i => $url) {
                    $media = new PropertyMedia();
                    $media->setProperty($property);
                    $media->setMediaType('image');
                    $media->setFileUrl($url);
                    $media->setSortOrder($i);
                    $media->setIsCover($i === 0);
                    $em->persist($media);
                }
            }

            try {
                $em->flush();
                $this->addFlash('success', 'Votre logement a bien été soumis à notre équipe. Vous serez notifié dès qu\'il aura été validé.');
                return $this->redirectToRoute('host_my_properties');
            } catch (\Exception) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'enregistrement. Veuillez réessayer.');
            }
        }

        return $this->render('host/become_host.html.twig', [
            'form'          => $form,
            'promoteToHost' => $promoteToHost,
        ]);
    }
}
