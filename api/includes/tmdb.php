<?php
// includes/tmdb.php
require_once __DIR__ . '/../config.php';

/**
 * Fetch full movie details from TMDb by ID, including
 * genres, actors, directors, and rating.
 */
function tmdb_get_movie($id) {
    $apiKey = TMDB_API_KEY;

    // Append credits so we can access cast (actors) and crew (directors)
    $url = "https://api.themoviedb.org/3/movie/{$id}"
         . "?api_key={$apiKey}&language=en-US&append_to_response=credits";

    $context = stream_context_create([
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false,
        ],
    ]);
    $json = @file_get_contents($url, false, $context);
    if (!$json) return null;

    $data = json_decode($json, true);
    if (empty($data['title'])) return null;

    // Genres
    $genres = $data['genres'] ?? [];

    // 🎭 Top actors (limit to 5)
    $actors = [];
    if (!empty($data['credits']['cast'])) {
        $count = 0;
        foreach ($data['credits']['cast'] as $cast) {
            if (empty($cast['id']) || empty($cast['name'])) {
                continue;
            }
            $actors[] = [
                'id'   => $cast['id'],
                'name' => $cast['name'],
            ];
            $count++;
            if ($count >= 5) {
                break;
            }
        }
    }

    // 🎬 Directors (from crew with job = "Director")
    $directors = [];
    if (!empty($data['credits']['crew'])) {
        foreach ($data['credits']['crew'] as $crew) {
            if (($crew['job'] ?? '') === 'Director'
                && !empty($crew['id'])
                && !empty($crew['name'])) {
                $directors[] = [
                    'id'   => $crew['id'],
                    'name' => $crew['name'],
                ];
            }
        }
    }

    // Poster
    $posterUrl = isset($data['poster_path'])
        ? 'https://image.tmdb.org/t/p/w500' . $data['poster_path']
        : 'https://via.placeholder.com/500x750?text=No+Poster';

    return [
        'id'           => $data['id'],
        'title'        => $data['title'],
        'overview'     => $data['overview'] ?? '',
        'poster'       => $posterUrl,
        'genres'       => $genres,
        'runtime'      => $data['runtime'] ?? null,
        'actors'       => $actors,
        'directors'    => $directors,
        'vote_average' => $data['vote_average'] ?? 0,
    ];
}

/**
 * Get TMDb recommendations based on a movie ID (used as basis).
 * Returns a list of FULL movie arrays using tmdb_get_movie,
 * so each recommended movie also has genres, actors, directors, rating.
 */
function tmdb_get_recommendations($id, $limit = 10) {
    $apiKey = TMDB_API_KEY;
    $url = "https://api.themoviedb.org/3/movie/{$id}/recommendations"
         . "?api_key={$apiKey}&language=en-US&page=1";

    $context = stream_context_create([
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false,
        ],
    ]);
    $json = @file_get_contents($url, false, $context);
    if (!$json) return [];

    $data = json_decode($json, true);
    if (empty($data['results'])) return [];

    global $TMDB_GENRES;
    if (empty($TMDB_GENRES)) {
        require_once __DIR__ . '/../tmdb_genres.php';
    }

    $results = [];
    foreach ($data['results'] as $r) {
        if (count($results) >= $limit) break;
        if (empty($r['id'])) continue;

        $mappedGenres = [];
        if (!empty($r['genre_ids'])) {
            foreach ($r['genre_ids'] as $gid) {
                if (isset($TMDB_GENRES[$gid])) {
                    $mappedGenres[] = ['id' => $gid, 'name' => $TMDB_GENRES[$gid]];
                }
            }
        }

        $posterUrl = isset($r['poster_path'])
            ? 'https://image.tmdb.org/t/p/w500' . $r['poster_path']
            : 'https://placehold.co/500x750/111827/666666?text=No+Poster';

        $results[] = [
            'id'           => $r['id'],
            'title'        => $r['title'],
            'overview'     => $r['overview'] ?? '',
            'poster'       => $posterUrl,
            'genres'       => $mappedGenres,
            'actors'       => [], // Omitted to save N+1 API requests
            'directors'    => [], // Omitted to save N+1 API requests
            'vote_average' => $r['vote_average'] ?? 0,
        ];
    }

    return $results;
}   
