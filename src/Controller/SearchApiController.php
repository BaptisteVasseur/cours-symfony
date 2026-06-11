<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/search', name: 'api_search_')]
final class SearchApiController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('/suggestions', name: 'suggestions', methods: ['GET'])]
    public function suggestions(Request $request): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));

        if (mb_strlen($q) < 2) {
            return $this->json([]);
        }

        $conn = $this->em->getConnection();

        // Distinct cities matching the query, from published properties only
        $rows = $conn->fetchAllAssociative(
            "SELECT DISTINCT a.city, a.country
             FROM property_addresses a
             INNER JOIN properties p ON p.id = a.property_id
             WHERE p.status = 'published'
               AND (LOWER(a.city) LIKE LOWER(:q) OR LOWER(a.country) LIKE LOWER(:q))
             ORDER BY a.city
             LIMIT 8",
            ['q' => '%' . $q . '%'],
        );

        $suggestions = array_map(
            fn(array $row) => [
                'label' => $row['city'] . ', ' . $row['country'],
                'value' => $row['city'],
            ],
            $rows,
        );

        return $this->json($suggestions);
    }
}
