<?php

declare(strict_types=1);

namespace App\Controller\Front\Host;

use App\Entity\Property;
use App\Entity\PropertyAddress;
use App\Entity\PropertyMedia;
use App\Entity\PropertyRule;
use App\Entity\User;
use App\Form\HostPropertyType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/hote/logements')]
#[IsGranted('ROLE_USER')]
final class PropertyController extends AbstractController
{
    #[Route('/nouveau', name: 'app_host_property_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $property = new Property();
        $property->setHost($user);
        $property->setStatus('draft');

        $form = $this->createForm(HostPropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $request->request->all('host_property');
            $city    = trim((string) ($formData['city'] ?? ''));
            $country = trim((string) ($formData['country'] ?? ''));

            $address = new PropertyAddress();
            $address->setCity($city ?: 'Non précisé');
            $address->setCountry($country ?: 'France');
            $address->setAddressLine1('À renseigner');
            $address->setPostalCode('00000');
            $address->setLatitude('0');
            $address->setLongitude('0');
            $property->setAddress($address);

            $rules = new PropertyRule();
            $rules->setPetsAllowed(false);
            $rules->setSmokingAllowed(false);
            $rules->setPartiesAllowed(false);
            $property->setRules($rules);

            $em->persist($property);

            // Gestion des photos uploadées
            /** @var UploadedFile[] $photos */
            $photos = $form->get('photos')->getData();
            $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/properties';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            foreach ($photos as $sortOrder => $photo) {
                if (!$photo instanceof UploadedFile) {
                    continue;
                }

                $originalFilename = pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename  = $safeFilename.'-'.uniqid().'.'.$photo->guessExtension();

                $photo->move($uploadDir, $newFilename);

                $media = new PropertyMedia();
                $media->setProperty($property);
                $media->setMediaType('image');
                $media->setFileUrl('/uploads/properties/'.$newFilename);
                $media->setSortOrder((int) $sortOrder);
                $media->setIsCover($sortOrder === 0);
                $em->persist($media);
            }

            $em->flush();

            $this->addFlash('success', 'Logement créé avec succès ! Il est en brouillon et sera publié après validation.');
            return $this->redirectToRoute('app_account_properties');
        }

        return $this->render('front/host/property/new.html.twig', [
            'form' => $form,
        ]);
    }
}
