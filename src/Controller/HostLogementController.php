<?php

namespace App\Controller;

use App\Entity\Adresse;
use App\Entity\Disponibilite;
use App\Entity\Logement;
use App\Entity\PhotoLogement;
use App\Entity\PolitiqueAnnulation;
use App\Entity\ReglementInterieur;
use App\Entity\Tarif;
use App\Entity\User;
use App\Enum\DisponibiliteStatut;
use App\Enum\LogementCategorie;
use App\Enum\LogementStatut;
use App\Enum\LogementType;
use App\Enum\ModerationStatut;
use App\Service\LogementPublicationValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hote')]
#[IsGranted('ROLE_HOTE')]
class HostLogementController extends AbstractController
{
    #[Route('/annonces', name: 'app_host_logement_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        $logements = $entityManager
            ->getRepository(Logement::class)
            ->findBy(['hote' => $user], ['dateMiseAJour' => 'DESC']);

        return $this->render('host/logement/index.html.twig', [
            'logements' => $logements,
        ]);
    }

    #[Route('/annonces/nouvelle', name: 'app_host_logement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('GET')) {
            return $this->render('host/logement/new.html.twig', [
                'types' => LogementType::cases(),
                'categories' => LogementCategorie::cases(),
            ]);
        }

        if (!$this->isCsrfTokenValid('host_logement_new', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le formulaire a expire. Reessayez.');

            return $this->redirectToRoute('app_host_logement_new');
        }

        $user = $this->getUser();
        \assert($user instanceof User);

        $erreurs = $this->validerFormulaire($request);

        if ($erreurs !== []) {
            foreach ($erreurs as $erreur) {
                $this->addFlash('error', $erreur);
            }

            return $this->redirectToRoute('app_host_logement_new');
        }

        $logement = new Logement();
        $logement->hote = $user;
        $logement->titre = trim((string) $request->request->get('titre'));
        $logement->description = trim((string) $request->request->get('description'));
        $logement->typeLogement = LogementType::from((string) $request->request->get('type_logement'));
        $logement->categorie = LogementCategorie::from((string) $request->request->get('categorie'));
        $logement->capaciteVoyageurs = max(1, (int) $request->request->get('capacite_voyageurs'));
        $logement->nombreChambres = max(0, (int) $request->request->get('nombre_chambres'));
        $logement->nombreLits = max(1, (int) $request->request->get('nombre_lits'));
        $logement->nombreSallesBain = $this->decimal((string) $request->request->get('nombre_salles_bain', '1'));
        $logement->surface = $this->decimalNullable((string) $request->request->get('surface', ''));
        $logement->statut = LogementStatut::BROUILLON;

        $adresse = new Adresse();
        $adresse->logement = $logement;
        $adresse->adresseLigne1 = trim((string) $request->request->get('adresse_ligne1'));
        $adresse->adresseLigne2 = $this->texteNullable((string) $request->request->get('adresse_ligne2', ''));
        $adresse->ville = trim((string) $request->request->get('ville'));
        $adresse->codePostal = trim((string) $request->request->get('code_postal'));
        $adresse->region = $this->texteNullable((string) $request->request->get('region', ''));
        $adresse->pays = trim((string) $request->request->get('pays'));
        $adresse->latitude = $this->coordonneeNullable((string) $request->request->get('latitude', ''));
        $adresse->longitude = $this->coordonneeNullable((string) $request->request->get('longitude', ''));
        $logement->adresse = $adresse;

        $tarif = new Tarif();
        $tarif->logement = $logement;
        $tarif->prixNuit = $this->decimal((string) $request->request->get('prix_nuit'));
        $tarif->fraisMenage = $this->decimal((string) $request->request->get('frais_menage', '0'));
        $tarif->depotGarantie = $this->decimal((string) $request->request->get('depot_garantie', '0'));
        $logement->tarif = $tarif;

        $reglement = new ReglementInterieur();
        $reglement->logement = $logement;
        $reglement->animauxAcceptes = $request->request->has('animaux_acceptes');
        $reglement->fumeursAcceptes = $request->request->has('fumeurs_acceptes');
        $reglement->fetesAutorisees = $request->request->has('fetes_autorisees');
        $reglement->enfantsAcceptes = $request->request->has('enfants_acceptes');
        $reglement->reglesSupplementaires = $this->texteNullable((string) $request->request->get('regles_supplementaires', ''));
        $logement->reglementInterieur = $reglement;

        $entityManager->persist($logement);
        $entityManager->persist($adresse);
        $entityManager->persist($tarif);
        $entityManager->persist($reglement);
        $entityManager->flush();

        $this->addFlash('success', 'Annonce creee en brouillon. Ajoutez ensuite photos, disponibilites et politique d annulation avant publication.');

        return $this->redirectToRoute('app_host_logement_index');
    }

    #[Route('/annonces/{id}', name: 'app_host_logement_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Logement $logement): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        if ($logement->hote !== $user) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('host/logement/show.html.twig', [
            'logement' => $logement,
        ]);
    }


    #[Route('/annonces/{id}/publication', name: 'app_host_logement_publication', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function publication(
        Logement $logement,
        Request $request,
        EntityManagerInterface $entityManager,
        LogementPublicationValidator $publicationValidator,
    ): Response {
        $this->verifierAccesHote($logement);

        if ($request->isMethod('GET')) {
            return $this->render('host/logement/publication.html.twig', [
                'logement' => $logement,
                'politiques' => $this->getOuCreerPolitiquesDisponibles($entityManager),
                'motifs_invalides' => $publicationValidator->getMotifsInvalides($logement),
            ]);
        }

        if (!$this->isCsrfTokenValid('host_logement_publication_'.$logement->id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Le formulaire a expire. Reessayez.');

            return $this->redirectToRoute('app_host_logement_publication', ['id' => $logement->id]);
        }

        $donneesEnregistrees = false;

        $photoFichier = $request->files->get('photo_fichier');
        if ($photoFichier instanceof UploadedFile) {
            if (!$logement->photos->isEmpty()) {
                $this->addFlash('error', 'Une photo principale est deja renseignee pour cette annonce.');
            } else {
                $photoUrl = $this->enregistrerPhotoLogement($photoFichier);

                if ($photoUrl === null) {
                    return $this->redirectToRoute('app_host_logement_publication', ['id' => $logement->id]);
                }

                $photo = new PhotoLogement();
                $photo->logement = $logement;
                $photo->url = $photoUrl;
                $photo->titre = $logement->titre;
                $photo->photoPrincipale = true;
                $photo->statutModeration = ModerationStatut::EN_ATTENTE;
                $logement->photos->add($photo);
                $entityManager->persist($photo);
                $donneesEnregistrees = true;
            }
        }

        $politiqueId = (int) $request->request->get('politique_annulation_id', 0);
        if ($politiqueId > 0) {
            $politique = $entityManager->getRepository(PolitiqueAnnulation::class)->find($politiqueId);
            if ($politique instanceof PolitiqueAnnulation && $politique->actif) {
                $logement->politiqueAnnulation = $politique;
                $donneesEnregistrees = true;
            } else {
                $this->addFlash('error', 'La politique d annulation selectionnee est invalide.');
            }
        }

        if ($logement->disponibilites->isEmpty()) {
            $dateDebut = $this->creerDate((string) $request->request->get('disponibilite_debut', ''));
            $dateFin = $this->creerDate((string) $request->request->get('disponibilite_fin', ''));

            if ($dateDebut !== null && $dateFin !== null) {
                if ($dateDebut > $dateFin) {
                    $this->addFlash('error', 'La date de fin doit etre posterieure ou egale a la date de debut.');
                } else {
                    $date = $dateDebut;
                    while ($date <= $dateFin) {
                        $disponibilite = new Disponibilite();
                        $disponibilite->logement = $logement;
                        $disponibilite->date = $date;
                        $disponibilite->statut = DisponibiliteStatut::DISPONIBLE;
                        $logement->disponibilites->add($disponibilite);
                        $entityManager->persist($disponibilite);
                        $date = $date->modify('+1 day');
                    }
                    $donneesEnregistrees = true;
                }
            }
        }

        if ($donneesEnregistrees) {
            $logement->dateMiseAJour = new \DateTimeImmutable();
            $entityManager->flush();
        }

        $motifsInvalides = $publicationValidator->getMotifsInvalides($logement);
        if ($motifsInvalides !== []) {
            foreach ($motifsInvalides as $motif) {
                $this->addFlash('error', $motif);
            }

            if ($donneesEnregistrees) {
                $this->addFlash('success', 'Les elements renseignes ont ete enregistres. Completez les points restants avant soumission.');
            }

            return $this->redirectToRoute('app_host_logement_publication', ['id' => $logement->id]);
        }

        $logement->statut = LogementStatut::EN_ATTENTE;
        $logement->dateMiseAJour = new \DateTimeImmutable();
        $entityManager->flush();

        $this->addFlash('success', 'Annonce soumise a moderation. Elle sera visible apres validation administrateur.');

        return $this->redirectToRoute('app_host_logement_show', ['id' => $logement->id]);
    }


    /**
     * @return list<PolitiqueAnnulation>
     */
    private function getOuCreerPolitiquesDisponibles(EntityManagerInterface $entityManager): array
    {
        $repository = $entityManager->getRepository(PolitiqueAnnulation::class);
        $politiques = $repository->findBy(['actif' => true], ['nom' => 'ASC']);

        if ($politiques !== []) {
            return $politiques;
        }

        $flexible = new PolitiqueAnnulation();
        $flexible->nom = 'Flexible';
        $flexible->description = 'Remboursement complet jusqu a 24h avant l arrivee.';
        $flexible->delaiRemboursementTotal = 1;
        $flexible->delaiRemboursementPartiel = 0;
        $flexible->pourcentageRemboursementPartiel = '100.00';
        $flexible->fraisServiceRemboursables = true;

        $moderee = new PolitiqueAnnulation();
        $moderee->nom = 'Moderee';
        $moderee->description = 'Remboursement partiel jusqu a 5 jours avant l arrivee.';
        $moderee->delaiRemboursementTotal = 5;
        $moderee->delaiRemboursementPartiel = 2;
        $moderee->pourcentageRemboursementPartiel = '50.00';
        $moderee->fraisServiceRemboursables = true;

        $entityManager->persist($flexible);
        $entityManager->persist($moderee);
        $entityManager->flush();

        return [$flexible, $moderee];
    }

    private function enregistrerPhotoLogement(UploadedFile $fichier): ?string
    {
        if (!$fichier->isValid()) {
            $this->addFlash('error', 'Le fichier envoye est invalide.');

            return null;
        }

        if (!str_starts_with((string) $fichier->getMimeType(), 'image/')) {
            $this->addFlash('error', 'Le fichier doit etre une image.');

            return null;
        }

        if ($fichier->getSize() !== false && $fichier->getSize() > 5 * 1024 * 1024) {
            $this->addFlash('error', 'La photo ne doit pas depasser 5 Mo.');

            return null;
        }

        $extension = $fichier->guessExtension() ?: 'jpg';
        $nomFichier = bin2hex(random_bytes(16)).'.'.$extension;
        $dossier = $this->getParameter('kernel.project_dir').'/public/uploads/logements';

        if (!is_dir($dossier)) {
            mkdir($dossier, 0775, true);
        }

        $fichier->move($dossier, $nomFichier);

        return '/uploads/logements/'.$nomFichier;
    }

    private function verifierAccesHote(Logement $logement): void
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        if ($logement->hote->id !== $user->id) {
            throw $this->createAccessDeniedException();
        }
    }

    private function creerDate(string $valeur): ?\DateTimeImmutable
    {
        if ($valeur === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

        return $date instanceof \DateTimeImmutable ? $date : null;
    }

    /**
     * @return list<string>
     */
    private function validerFormulaire(Request $request): array
    {
        $erreurs = [];

        foreach (['titre', 'description', 'adresse_ligne1', 'ville', 'code_postal', 'pays', 'prix_nuit'] as $champ) {
            if (trim((string) $request->request->get($champ, '')) === '') {
                $erreurs[] = 'Le champ '.$champ.' est obligatoire.';
            }
        }

        if (!LogementType::tryFrom((string) $request->request->get('type_logement'))) {
            $erreurs[] = 'Le type de logement est invalide.';
        }

        if (!LogementCategorie::tryFrom((string) $request->request->get('categorie'))) {
            $erreurs[] = 'La categorie de logement est invalide.';
        }

        if ((int) $request->request->get('capacite_voyageurs', 0) < 1) {
            $erreurs[] = 'La capacite doit etre superieure a zero.';
        }

        if ((float) str_replace(',', '.', (string) $request->request->get('prix_nuit', '0')) <= 0) {
            $erreurs[] = 'Le prix par nuit doit etre superieur a zero.';
        }

        $latitude = $this->nombreNullable((string) $request->request->get('latitude', ''));
        if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
            $erreurs[] = 'La latitude doit etre comprise entre -90 et 90.';
        }

        $longitude = $this->nombreNullable((string) $request->request->get('longitude', ''));
        if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
            $erreurs[] = 'La longitude doit etre comprise entre -180 et 180.';
        }

        return $erreurs;
    }

    private function decimal(string $valeur): string
    {
        return number_format((float) str_replace(',', '.', $valeur), 2, '.', '');
    }

    private function decimalNullable(string $valeur): ?string
    {
        $valeur = trim($valeur);

        return $valeur === '' ? null : $this->decimal($valeur);
    }

    private function coordonneeNullable(string $valeur): ?string
    {
        $nombre = $this->nombreNullable($valeur);

        return $nombre === null ? null : number_format($nombre, 7, '.', '');
    }

    private function nombreNullable(string $valeur): ?float
    {
        $valeur = trim(str_replace(',', '.', $valeur));

        return $valeur === '' || !is_numeric($valeur) ? null : (float) $valeur;
    }

    private function texteNullable(string $valeur): ?string
    {
        $valeur = trim($valeur);

        return $valeur === '' ? null : $valeur;
    }
}
