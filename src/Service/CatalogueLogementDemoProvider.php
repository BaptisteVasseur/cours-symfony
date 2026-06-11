<?php

namespace App\Service;

class CatalogueLogementDemoProvider
{
    /**
     * @return list<array{
     *     slug: string,
     *     titre: string,
     *     ville: string,
     *     pays: string,
     *     type: string,
     *     categorie: string,
     *     image: string,
     *     prixNuit: int,
     *     note: float,
     *     nombreAvis: int,
     *     capacite: int,
     *     chambres: int,
     *     lits: int,
     *     sallesBain: float,
     *     description: string,
     *     equipements: list<string>,
     *     hote: string,
     *     politiqueAnnulation: string,
     *     regles: list<string>
     * }>
     */
    public function tous(): array
    {
        return [
            [
                'slug' => 'loft-lumineux-paris-canal',
                'titre' => 'Loft lumineux pres du canal',
                'ville' => 'Paris',
                'pays' => 'France',
                'type' => 'Appartement',
                'categorie' => 'Logement entier',
                'image' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1200&q=80',
                'prixNuit' => 142,
                'note' => 4.86,
                'nombreAvis' => 128,
                'capacite' => 4,
                'chambres' => 2,
                'lits' => 2,
                'sallesBain' => 1.0,
                'description' => 'Un appartement calme et lumineux, proche des transports, avec une cuisine equipee et un espace de travail confortable.',
                'equipements' => ['Wifi', 'Cuisine', 'Lave-linge', 'Espace de travail', 'Arrivee autonome'],
                'hote' => 'Camille',
                'politiqueAnnulation' => 'Moderee',
                'regles' => ['Non fumeur', 'Pas de fetes', 'Animaux sur demande'],
            ],
            [
                'slug' => 'villa-piscine-nice',
                'titre' => 'Villa avec piscine et vue mer',
                'ville' => 'Nice',
                'pays' => 'France',
                'type' => 'Villa',
                'categorie' => 'Logement entier',
                'image' => 'https://images.unsplash.com/photo-1499793983690-e29da59ef1c2?auto=format&fit=crop&w=1200&q=80',
                'prixNuit' => 318,
                'note' => 4.92,
                'nombreAvis' => 74,
                'capacite' => 8,
                'chambres' => 4,
                'lits' => 5,
                'sallesBain' => 3.0,
                'description' => 'Grande villa familiale avec terrasse, piscine securisee et acces rapide aux plages.',
                'equipements' => ['Piscine', 'Parking', 'Climatisation', 'Barbecue', 'Vue mer'],
                'hote' => 'Nadia',
                'politiqueAnnulation' => 'Stricte',
                'regles' => ['Respect du voisinage', 'Pas de soirees', 'Depart avant 11h'],
            ],
            [
                'slug' => 'chalet-bois-annecy',
                'titre' => 'Chalet bois proche du lac',
                'ville' => 'Annecy',
                'pays' => 'France',
                'type' => 'Chalet',
                'categorie' => 'Logement entier',
                'image' => 'https://images.unsplash.com/photo-1518780664697-55e3ad937233?auto=format&fit=crop&w=1200&q=80',
                'prixNuit' => 206,
                'note' => 4.79,
                'nombreAvis' => 96,
                'capacite' => 6,
                'chambres' => 3,
                'lits' => 4,
                'sallesBain' => 2.0,
                'description' => 'Chalet chaleureux pour sejour nature, avec poele, jardin et acces facile aux sentiers.',
                'equipements' => ['Cheminee', 'Jardin', 'Cuisine', 'Parking', 'Local velo'],
                'hote' => 'Hugo',
                'politiqueAnnulation' => 'Flexible',
                'regles' => ['Animaux acceptes', 'Non fumeur', 'Calme apres 22h'],
            ],
            [
                'slug' => 'studio-centre-lyon',
                'titre' => 'Studio central pour city break',
                'ville' => 'Lyon',
                'pays' => 'France',
                'type' => 'Studio',
                'categorie' => 'Logement entier',
                'image' => 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=1200&q=80',
                'prixNuit' => 88,
                'note' => 4.64,
                'nombreAvis' => 211,
                'capacite' => 2,
                'chambres' => 1,
                'lits' => 1,
                'sallesBain' => 1.0,
                'description' => 'Studio fonctionnel dans un quartier vivant, ideal pour visiter la ville a pied.',
                'equipements' => ['Wifi', 'Cuisine', 'Ascenseur', 'Cafe', 'Seche-cheveux'],
                'hote' => 'Sarah',
                'politiqueAnnulation' => 'Flexible',
                'regles' => ['Non fumeur', 'Pas de fetes', 'Arrivee apres 15h'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rechercher(array $criteres): array
    {
        return array_values(array_filter($this->tous(), static function (array $logement) use ($criteres): bool {
            $destination = strtolower(trim((string) ($criteres['destination'] ?? '')));
            $type = strtolower(trim((string) ($criteres['type'] ?? '')));
            $voyageurs = (int) ($criteres['voyageurs'] ?? 0);
            $prixMax = (int) ($criteres['prix_max'] ?? 0);

            if ($destination !== '' && !str_contains(strtolower($logement['ville'].' '.$logement['pays']), $destination)) {
                return false;
            }

            if ($type !== '' && strtolower($logement['type']) !== $type) {
                return false;
            }

            if ($voyageurs > 0 && $logement['capacite'] < $voyageurs) {
                return false;
            }

            if ($prixMax > 0 && $logement['prixNuit'] > $prixMax) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trouverParSlug(string $slug): ?array
    {
        foreach ($this->tous() as $logement) {
            if ($logement['slug'] === $slug) {
                return $logement;
            }
        }

        return null;
    }
}
