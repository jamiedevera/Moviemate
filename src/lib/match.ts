import type { TmdbMovie, TmdbRec } from "./tmdb";

interface Profile {
  genres:    Record<string, number>;
  actors:    Record<number, number>;
  directors: Record<number, number>;
}

export function buildProfile(movies: TmdbMovie[]): Profile {
  const genres:    Record<string, number> = {};
  const actors:    Record<number, number> = {};
  const directors: Record<number, number> = {};

  for (const movie of movies) {
    const w = 1.0;
    for (const g of movie.genres ?? [])    genres[g.name]    = (genres[g.name]    ?? 0) + w;
    for (const a of movie.actors ?? [])    actors[a.id]      = (actors[a.id]      ?? 0) + w;
    for (const d of movie.directors ?? []) directors[d.id]   = (directors[d.id]   ?? 0) + w;
  }

  return { genres, actors, directors };
}

function profileOverlap(
  a: Record<string | number, number>,
  b: Record<string | number, number>,
  cap: number
): number {
  const keysA = new Set(Object.keys(a));
  const shared = Object.keys(b).filter((k) => keysA.has(k)).length;
  if (cap <= 0) return 0;
  return Math.min(1, shared / cap);
}

export function computeCompatibility(
  profileA: Profile,
  profileB: Profile,
  sharedCount: number,
  maxPicks: number
): number {
  const sharedRatio = maxPicks > 0 ? sharedCount / maxPicks : 0;

  const genreCap    = Math.max(1, Math.min(8,  Math.max(Object.keys(profileA.genres).length,    Object.keys(profileB.genres).length)));
  const actorCap    = Math.max(1, Math.min(10, Math.max(Object.keys(profileA.actors).length,    Object.keys(profileB.actors).length)));
  const directorCap = Math.max(1, Math.min(4,  Math.max(Object.keys(profileA.directors).length, Object.keys(profileB.directors).length)));

  const genreOverlap    = profileOverlap(profileA.genres,    profileB.genres,    genreCap);
  const actorOverlap    = profileOverlap(profileA.actors,    profileB.actors,    actorCap);
  const directorOverlap = profileOverlap(profileA.directors, profileB.directors, directorCap);

  const pct = 20
    + 45 * sharedRatio
    + 20 * genreOverlap
    + 10 * actorOverlap
    +  5 * directorOverlap;

  return Math.round(Math.max(0, Math.min(100, pct)));
}

export function scoreCandidate(
  movie: TmdbRec,
  profileA: Profile,
  profileB: Profile,
  baseCount: number
): number {
  let gA = 0, gB = 0, aA = 0, aB = 0, dA = 0, dB = 0;

  for (const g of movie.genres ?? []) {
    gA += profileA.genres[g.name] ?? 0;
    gB += profileB.genres[g.name] ?? 0;
  }
  for (const a of movie.actors ?? []) {
    aA += profileA.actors[a.id] ?? 0;
    aB += profileB.actors[a.id] ?? 0;
  }
  for (const d of movie.directors ?? []) {
    dA += profileA.directors[d.id] ?? 0;
    dB += profileB.directors[d.id] ?? 0;
  }

  return (
    baseCount * 2.0 +
    (gA + gB)  * 0.7 +
    (aA + aB)  * 1.0 +
    (dA + dB)  * 1.2 +
    (movie.vote_average / 10) * 1.0
  );
}
