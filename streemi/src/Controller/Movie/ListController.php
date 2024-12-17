<?php

declare(strict_types=1);

namespace App\Controller\Movie;

use App\Repository\PlaylistRepository;
use App\Repository\PlaylistSubscriptionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ListController extends AbstractController
{
    #[Route('/lists', name: 'page_lists')]
    public function index(
        PlaylistRepository $playlistRepository,
        PlaylistSubscriptionRepository $playlistSubscriptionRepository,
        Request $request
    ): Response
    {
        $myPlaylists = $playlistRepository->findAll();
        $mySubscribedPlaylists = $playlistSubscriptionRepository->findAll();

        $playlistId = $request->query->get('playlist');

        if ($playlistId) {
            $playlist = $playlistRepository->find($playlistId);
        } else {
            $playlist = null;
        }

        return $this->render('other/lists.html.twig', [
            'myPlaylists' => $myPlaylists,
            'mySubscribedPlaylists' => $mySubscribedPlaylists,
            'activePlaylist' => $playlist,
        ]);
    }
}
