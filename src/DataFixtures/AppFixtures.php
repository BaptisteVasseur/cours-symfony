<?php

namespace App\DataFixtures;

use App\Entity\Adresse;
use App\Entity\Disponibilite;
use App\Entity\Equipement;
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
use App\Enum\UserRole;
use App\Enum\UserStatut;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $hote = $this->creerUtilisateur('Camille', 'Martin', 'hote@stayshare.test', UserRole::HOTE);
        $voyageur = $this->creerUtilisateur('Alex', 'Durand', 'voyageur@stayshare.test', UserRole::VOYAGEUR);
        $admin = $this->creerUtilisateur('Admin', 'StayShare', 'admin@stayshare.test', UserRole::ADMIN);

        $manager->persist($hote);
        $manager->persist($voyageur);
        $manager->persist($admin);

        $wifi = $this->creerEquipement('Wifi', 'Confort');
        $cuisine = $this->creerEquipement('Cuisine', 'Confort');
        $parking = $this->creerEquipement('Parking', 'Transport');
        $piscine = $this->creerEquipement('Piscine', 'Loisir');
        $climatisation = $this->creerEquipement('Climatisation', 'Confort');

        foreach ([$wifi, $cuisine, $parking, $piscine, $climatisation] as $equipement) {
            $manager->persist($equipement);
        }

        $flexible = $this->creerPolitique('Flexible', 'Remboursement complet jusqu a 24h avant l arrivee.', 1, 0, '100.00');
        $moderee = $this->creerPolitique('Moderee', 'Remboursement partiel jusqu a 5 jours avant l arrivee.', 5, 2, '50.00');

        $manager->persist($flexible);
        $manager->persist($moderee);

        $logements = [
            $this->creerLogement(
                hote: $hote,
                titre: 'Loft lumineux pres du canal',
                description: 'Appartement calme et lumineux, proche des transports, avec cuisine equipee et espace de travail.',
                type: LogementType::APPARTEMENT,
                categorie: LogementCategorie::LOGEMENT_ENTIER,
                ville: 'Paris',
                codePostal: '75010',
                pays: 'France',
                prixNuit: '142.00',
                image: 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1200&q=80',
                capacite: 4,
                chambres: 2,
                lits: 2,
                sallesBain: '1.0',
                politique: $moderee,
                equipements: [$wifi, $cuisine, $climatisation],
                instantBooking: false,
            ),
            $this->creerLogement(
                hote: $hote,
                titre: 'Villa avec piscine et vue mer',
                description: 'Grande villa familiale avec terrasse, piscine securisee et acces rapide aux plages.',
                type: LogementType::VILLA,
                categorie: LogementCategorie::LOGEMENT_ENTIER,
                ville: 'Nice',
                codePostal: '06000',
                pays: 'France',
                prixNuit: '318.00',
                image: 'https://images.unsplash.com/photo-1499793983690-e29da59ef1c2?auto=format&fit=crop&w=1200&q=80',
                capacite: 8,
                chambres: 4,
                lits: 5,
                sallesBain: '3.0',
                politique: $moderee,
                equipements: [$wifi, $cuisine, $parking, $piscine, $climatisation],
                instantBooking: true,
            ),
            $this->creerLogement(
                hote: $hote,
                titre: 'Studio central pour city break',
                description: 'Studio fonctionnel dans un quartier vivant, ideal pour visiter la ville a pied.',
                type: LogementType::STUDIO,
                categorie: LogementCategorie::LOGEMENT_ENTIER,
                ville: 'Lyon',
                codePostal: '69002',
                pays: 'France',
                prixNuit: '88.00',
                image: 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=1200&q=80',
                capacite: 2,
                chambres: 1,
                lits: 1,
                sallesBain: '1.0',
                politique: $flexible,
                equipements: [$wifi, $cuisine],
                instantBooking: true,
            ),
        ];

        foreach ($logements as $logement) {
            $manager->persist($logement);
            $manager->persist($logement->adresse);
            $manager->persist($logement->tarif);
            $manager->persist($logement->reglementInterieur);

            foreach ($logement->photos as $photo) {
                $manager->persist($photo);
            }

            foreach ($logement->disponibilites as $disponibilite) {
                $manager->persist($disponibilite);
            }
        }

        $manager->flush();
    }

    private function creerUtilisateur(string $prenom, string $nom, string $email, UserRole $role): User
    {
        $user = new User();
        $user->prenom = $prenom;
        $user->nom = $nom;
        $user->email = $email;
        $user->role = $role;
        $user->statut = UserStatut::ACTIF;
        $user->emailVerifie = true;
        $user->telephoneVerifie = true;
        $user->identiteVerifiee = true;
        $user->consentementCgu = true;
        $user->dateNaissance = new \DateTimeImmutable('1990-01-01');
        $user->motDePasseHash = $this->passwordHasher->hashPassword($user, 'Password123!');

        return $user;
    }

    private function creerEquipement(string $nom, string $categorie): Equipement
    {
        $equipement = new Equipement();
        $equipement->nom = $nom;
        $equipement->categorie = $categorie;

        return $equipement;
    }

    private function creerPolitique(string $nom, string $description, int $total, int $partiel, string $pourcentage): PolitiqueAnnulation
    {
        $politique = new PolitiqueAnnulation();
        $politique->nom = $nom;
        $politique->description = $description;
        $politique->delaiRemboursementTotal = $total;
        $politique->delaiRemboursementPartiel = $partiel;
        $politique->pourcentageRemboursementPartiel = $pourcentage;
        $politique->fraisServiceRemboursables = true;

        return $politique;
    }

    /**
     * @param list<Equipement> $equipements
     */
    private function creerLogement(
        User $hote,
        string $titre,
        string $description,
        LogementType $type,
        LogementCategorie $categorie,
        string $ville,
        string $codePostal,
        string $pays,
        string $prixNuit,
        string $image,
        int $capacite,
        int $chambres,
        int $lits,
        string $sallesBain,
        PolitiqueAnnulation $politique,
        array $equipements,
        bool $instantBooking,
    ): Logement {
        $logement = new Logement();
        $logement->hote = $hote;
        $logement->titre = $titre;
        $logement->description = $description;
        $logement->typeLogement = $type;
        $logement->categorie = $categorie;
        $logement->capaciteVoyageurs = $capacite;
        $logement->nombreChambres = $chambres;
        $logement->nombreLits = $lits;
        $logement->nombreSallesBain = $sallesBain;
        $logement->statut = LogementStatut::PUBLIE;
        $logement->instantBooking = $instantBooking;
        $logement->politiqueAnnulation = $politique;
        $logement->noteMoyenne = '4.80';
        $logement->nombreAvis = 12;
        $logement->datePublication = new \DateTimeImmutable('-10 days');

        $adresse = new Adresse();
        $adresse->logement = $logement;
        $adresse->adresseLigne1 = '10 rue de la Demo';
        $adresse->ville = $ville;
        $adresse->codePostal = $codePostal;
        $adresse->pays = $pays;
        $logement->adresse = $adresse;

        $tarif = new Tarif();
        $tarif->logement = $logement;
        $tarif->prixNuit = $prixNuit;
        $tarif->fraisMenage = '35.00';
        $tarif->depotGarantie = '200.00';
        $logement->tarif = $tarif;

        $reglement = new ReglementInterieur();
        $reglement->logement = $logement;
        $reglement->animauxAcceptes = false;
        $reglement->fumeursAcceptes = false;
        $reglement->fetesAutorisees = false;
        $reglement->enfantsAcceptes = true;
        $reglement->reglesSupplementaires = 'Respect du voisinage apres 22h.';
        $logement->reglementInterieur = $reglement;

        $photo = new PhotoLogement();
        $photo->logement = $logement;
        $photo->url = $image;
        $photo->photoPrincipale = true;
        $photo->statutModeration = ModerationStatut::VALIDEE;
        $logement->photos->add($photo);

        foreach ($equipements as $equipement) {
            $logement->equipements->add($equipement);
        }

        for ($i = 1; $i <= 30; $i++) {
            $disponibilite = new Disponibilite();
            $disponibilite->logement = $logement;
            $disponibilite->date = new \DateTimeImmutable('+'.$i.' days');
            $disponibilite->statut = DisponibiliteStatut::DISPONIBLE;
            $logement->disponibilites->add($disponibilite);
        }

        return $logement;
    }
}
