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

    $json = http_get_contents($url);
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
 * Helper to fetch multiple URLs in parallel using curl_multi
 */
function fetch_urls_parallel($urls) {
    $mh = curl_multi_init();
    $ch_list = [];
    $results = [];

    foreach ($urls as $id => $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // Auto-decompress gzip/deflate
        curl_multi_add_handle($mh, $ch);
        $ch_list[$id] = $ch;
    }

    $active = null;
    do {
        $mrc = curl_multi_exec($mh, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);

    while ($active && $mrc == CURLM_OK) {
        if (curl_multi_select($mh) == -1) {
            usleep(1000); // Prevent 100% CPU on Windows
        }
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
    }

    foreach ($ch_list as $id => $ch) {
        $res = curl_multi_getcontent($ch);
        // Manual decompression fallback
        if ($res !== false && substr($res, 0, 2) === "\x1f\x8b") {
            $decompressed = @gzdecode($res);
            if ($decompressed !== false) {
                $res = $decompressed;
            }
        }
        $results[$id] = $res;
        curl_multi_remove_handle($mh, $ch);
    }
    curl_multi_close($mh);
    return $results;
}

/**
 * Fetch multiple movies in parallel
 */
function tmdb_get_movies_parallel(array $ids) {
    $apiKey = TMDB_API_KEY;
    $urls = [];
    foreach ($ids as $id) {
        $urls[$id] = "https://api.themoviedb.org/3/movie/{$id}?api_key={$apiKey}&language=en-US&append_to_response=credits";
    }

    $responses = fetch_urls_parallel($urls);
    $movies = [];

    foreach ($responses as $id => $json) {
        if (!$json) continue;
        $data = json_decode($json, true);
        if (empty($data['title'])) continue;

        $genres = $data['genres'] ?? [];
        $actors = [];
        if (!empty($data['credits']['cast'])) {
            $count = 0;
            foreach ($data['credits']['cast'] as $cast) {
                if (empty($cast['id']) || empty($cast['name'])) continue;
                $actors[] = ['id' => $cast['id'], 'name' => $cast['name']];
                if (++$count >= 5) break;
            }
        }
        $directors = [];
        if (!empty($data['credits']['crew'])) {
            foreach ($data['credits']['crew'] as $crew) {
                if (($crew['job'] ?? '') === 'Director' && !empty($crew['id']) && !empty($crew['name'])) {
                    $directors[] = ['id' => $crew['id'], 'name' => $crew['name']];
                }
            }
        }
        $posterUrl = isset($data['poster_path'])
            ? 'https://image.tmdb.org/t/p/w500' . $data['poster_path']
            : 'https://via.placeholder.com/500x750?text=No+Poster';

        $movies[$id] = [
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
    return $movies;
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

    $json = http_get_contents($url);
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

/**
 * Get TMDb recommendations for multiple seeds in parallel.
 */
function tmdb_get_recommendations_parallel(array $seedIds, $limit_per_seed = 10) {
    $apiKey = TMDB_API_KEY;
    $urls = [];
    foreach ($seedIds as $id) {
        $urls[$id] = "https://api.themoviedb.org/3/movie/{$id}/recommendations?api_key={$apiKey}&language=en-US&page=1";
    }

    $responses = fetch_urls_parallel($urls);
    $allResults = [];

    global $TMDB_GENRES;
    if (empty($TMDB_GENRES)) {
        require_once __DIR__ . '/../tmdb_genres.php';
    }

    foreach ($responses as $seedId => $json) {
        if (!$json) continue;
        $data = json_decode($json, true);
        if (empty($data['results'])) continue;

        $results = [];
        foreach ($data['results'] as $r) {
            if (count($results) >= $limit_per_seed) break;
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
                'actors'       => [],
                'directors'    => [],
                'vote_average' => $r['vote_average'] ?? 0,
            ];
        }
        $allResults[$seedId] = $results;
    }
    return $allResults;
}
