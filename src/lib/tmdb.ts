const TMDB_KEY = process.env.TMDB_API_KEY!;
const BASE = "https://api.themoviedb.org/3";

export const TMDB_GENRES: Record<number, string> = {
  28: "Action", 12: "Adventure", 16: "Animation", 35: "Comedy",
  80: "Crime", 99: "Documentary", 18: "Drama", 10751: "Family",
  14: "Fantasy", 36: "History", 27: "Horror", 10402: "Music",
  9648: "Mystery", 10749: "Romance", 878: "Science Fiction",
  10770: "TV Movie", 53: "Thriller", 10752: "War", 37: "Western",
};

export interface TmdbMovie {
  id: number;
  title: string;
  overview: string;
  poster: string;
  genres: { id: number; name: string }[];
  actors: { id: number; name: string }[];
  directors: { id: number; name: string }[];
  vote_average: number;
  runtime?: number | null;
}

function posterUrl(path: string | null | undefined): string {
  return path
    ? `https://image.tmdb.org/t/p/w500${path}`
    : "https://placehold.co/500x750/111827/666666?text=No+Poster";
}

function parseMovie(data: Record<string, unknown>): TmdbMovie | null {
  if (!data?.title) return null;

  const cast = (data.credits as Record<string, unknown[]>)?.cast ?? [];
  const crew = (data.credits as Record<string, unknown[]>)?.crew ?? [];

  const actors = (cast as Record<string, unknown>[])
    .filter((c) => c.id && c.name)
    .slice(0, 5)
    .map((c) => ({ id: c.id as number, name: c.name as string }));

  const directors = (crew as Record<string, unknown>[])
    .filter((c) => c.job === "Director" && c.id && c.name)
    .map((c) => ({ id: c.id as number, name: c.name as string }));

  return {
    id:           data.id as number,
    title:        data.title as string,
    overview:     (data.overview as string) ?? "",
    poster:       posterUrl(data.poster_path as string),
    genres:       (data.genres as { id: number; name: string }[]) ?? [],
    actors,
    directors,
    vote_average: (data.vote_average as number) ?? 0,
    runtime:      (data.runtime as number) ?? null,
  };
}

async function fetchJson(url: string): Promise<Record<string, unknown> | null> {
  try {
    const res = await fetch(url, { next: { revalidate: 0 } });
    if (!res.ok) return null;
    return await res.json();
  } catch {
    return null;
  }
}

export async function tmdbGetMovie(id: number): Promise<TmdbMovie | null> {
  const data = await fetchJson(
    `${BASE}/movie/${id}?api_key=${TMDB_KEY}&language=en-US&append_to_response=credits`
  );
  if (!data) return null;
  return parseMovie(data);
}

export async function tmdbGetMoviesParallel(
  ids: number[]
): Promise<Record<number, TmdbMovie>> {
  const results = await Promise.all(
    ids.map((id) =>
      fetchJson(
        `${BASE}/movie/${id}?api_key=${TMDB_KEY}&language=en-US&append_to_response=credits`
      ).then((data) => ({ id, movie: data ? parseMovie(data) : null }))
    )
  );

  const map: Record<number, TmdbMovie> = {};
  for (const { id, movie } of results) {
    if (movie) map[id] = movie;
  }
  return map;
}

export interface TmdbRec {
  id: number;
  title: string;
  overview: string;
  poster: string;
  genres: { id: number; name: string }[];
  actors: { id: number; name: string }[];
  directors: { id: number; name: string }[];
  vote_average: number;
}

function parseRec(r: Record<string, unknown>): TmdbRec {
  const genres = ((r.genre_ids as number[]) ?? [])
    .filter((gid) => TMDB_GENRES[gid])
    .map((gid) => ({ id: gid, name: TMDB_GENRES[gid] }));

  return {
    id:           r.id as number,
    title:        r.title as string,
    overview:     (r.overview as string) ?? "",
    poster:       posterUrl(r.poster_path as string),
    genres,
    actors:       [],
    directors:    [],
    vote_average: (r.vote_average as number) ?? 0,
  };
}

export async function tmdbGetRecommendationsParallel(
  seedIds: number[],
  limitPerSeed = 10
): Promise<Record<number, TmdbRec[]>> {
  const results = await Promise.all(
    seedIds.map((id) =>
      fetchJson(
        `${BASE}/movie/${id}/recommendations?api_key=${TMDB_KEY}&language=en-US&page=1`
      ).then((data) => ({
        id,
        recs: ((data?.results as Record<string, unknown>[]) ?? [])
          .slice(0, limitPerSeed)
          .filter((r) => r.id)
          .map(parseRec),
      }))
    )
  );

  const map: Record<number, TmdbRec[]> = {};
  for (const { id, recs } of results) {
    map[id] = recs;
  }
  return map;
}

export async function tmdbGetPopular(): Promise<TmdbRec[]> {
  const data = await fetchJson(
    `${BASE}/movie/popular?api_key=${TMDB_KEY}&language=en-US&page=1`
  );
  return ((data?.results as Record<string, unknown>[]) ?? []).map(parseRec);
}
