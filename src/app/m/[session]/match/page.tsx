import { notFound, redirect } from "next/navigation";
import { query } from "@/lib/db";
import {
  tmdbGetMoviesParallel,
  tmdbGetRecommendationsParallel,
  tmdbGetPopular,
  type TmdbMovie,
  type TmdbRec,
} from "@/lib/tmdb";
import {
  buildProfile,
  computeCompatibility,
  scoreCandidate,
} from "@/lib/match";
import MatchClient from "./MatchClient";

export const dynamic = "force-dynamic";

export default async function MatchPage({
  params,
}: {
  params: Promise<{ session: string }>;
}) {
  const { session } = await params;

  if (!session || !/^[a-f0-9]{16}$/.test(session)) {
    notFound();
  }

  const rows = await query<{ a_movies: string; b_movies: string }>(
    "SELECT a_movies, b_movies FROM sessions WHERE id = $1",
    [session]
  );

  if (!rows.length) notFound();

  const { a_movies, b_movies } = rows[0];

  // Not both done yet — show waiting state
  if (!a_movies || !b_movies) {
    redirect(`/m/${session}/waiting`);
  }

  const aIds: number[] = JSON.parse(a_movies);
  const bIds: number[] = JSON.parse(b_movies);
  const allIds = [...new Set([...aIds, ...bIds])];

  // Fetch all movie details in parallel
  const movieMap = await tmdbGetMoviesParallel(allIds);

  const personA: TmdbMovie[] = aIds.map((id) => movieMap[id]).filter(Boolean);
  const personB: TmdbMovie[] = bIds.map((id) => movieMap[id]).filter(Boolean);

  if (!personA.length || !personB.length) {
    notFound();
  }

  // Profiles
  const profileA = buildProfile(personA);
  const profileB = buildProfile(personB);

  // Shared picks
  const sharedIds  = aIds.filter((id) => bIds.includes(id));
  const sharedCount = sharedIds.length;
  const maxPicks    = Math.max(aIds.length, bIds.length);

  // Compatibility score
  const compatPercent = computeCompatibility(profileA, profileB, sharedCount, maxPicks);

  // Featured movie
  let recommendedMovie: TmdbMovie;
  if (sharedCount > 0) {
    recommendedMovie = movieMap[sharedIds[0]];
  } else {
    // Find best genre overlap pair
    let best: TmdbMovie | null = null;
    let bestScore = -1;
    for (const a of personA) {
      for (const b of personB) {
        const aGenres = new Set(a.genres.map((g) => g.name));
        const score   = b.genres.filter((g) => aGenres.has(g.name)).length;
        if (score > bestScore) { bestScore = score; best = a; }
      }
    }
    recommendedMovie = best ?? personA[0];
  }

  // Recommendations
  const seedIds = [...new Set([...aIds.slice(0, 4), ...bIds.slice(0, 4)])];
  const allPickedIds = new Set(allIds);

  const recsPerSeed = await tmdbGetRecommendationsParallel(seedIds, 8);
  const pool = new Map<number, { movie: TmdbRec; count: number }>();

  for (const recs of Object.values(recsPerSeed)) {
    for (const rec of recs) {
      if (allPickedIds.has(rec.id)) continue;
      const existing = pool.get(rec.id);
      if (existing) existing.count += 1;
      else pool.set(rec.id, { movie: rec, count: 1 });
    }
  }

  let scored = [...pool.values()].map(({ movie, count }) => ({
    movie,
    score: scoreCandidate(movie, profileA, profileB, count),
  }));

  scored.sort((a, b) => b.score - a.score);
  let topRecs: TmdbRec[] = scored.slice(0, 12).map((s) => s.movie);

  // Fallback
  if (topRecs.length < 4) {
    const popular = await tmdbGetPopular();
    for (const p of popular) {
      if (!allPickedIds.has(p.id) && topRecs.length < 12) topRecs.push(p);
    }
  }

  const initialRecs = topRecs.slice(0, 4);
  const extraRecs   = topRecs.slice(4).map((m) => ({
    title:    m.title,
    poster:   m.poster,
    overview: m.overview,
  }));

  return (
    <MatchClient
      compatPercent={compatPercent}
      sharedCount={sharedCount}
      recommendedMovie={recommendedMovie}
      initialRecs={initialRecs}
      extraRecs={extraRecs}
      personA={personA}
      personB={personB}
    />
  );
}
