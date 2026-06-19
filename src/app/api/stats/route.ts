import { NextResponse } from "next/server";
import { query } from "@/lib/db";

export const revalidate = 0;

export async function GET() {
  let matchesCount = 0;
  let moviesCount = 0;
  let popularMovies: {
    id: number;
    title: string;
    poster: string | null;
    rating: number;
    year: number | string;
  }[] = [];

  // ── DB: count completed sessions ──────────────────────────────────────────
  try {
    const rows = await query<{ count: string }>(
      `SELECT COUNT(*) FROM sessions
       WHERE a_movies IS NOT NULL AND a_movies != ''
       AND   b_movies IS NOT NULL AND b_movies != ''`
    );
    matchesCount = parseInt(rows[0]?.count ?? "0", 10);
  } catch (err) {
    console.error("db-stats error:", err);
  }

  // ── TMDb: popular movies ───────────────────────────────────────────────────
  const tmdbKey = process.env.TMDB_API_KEY;
  if (tmdbKey) {
    try {
      const tmdbRes = await fetch(
        `https://api.themoviedb.org/3/movie/popular?api_key=${tmdbKey}&page=1`,
        { next: { revalidate: 3600 } }
      );
      if (tmdbRes.ok) {
        const tmdbData = await tmdbRes.json();
        moviesCount = tmdbData.total_results ?? 0;
        if (Array.isArray(tmdbData.results)) {
          popularMovies = tmdbData.results.slice(0, 10).map((m: {
            id: number;
            title: string;
            poster_path: string | null;
            vote_average: number;
            release_date: string;
          }) => ({
            id: m.id,
            title: m.title,
            poster: m.poster_path
              ? `https://image.tmdb.org/t/p/w342${m.poster_path}`
              : null,
            rating: m.vote_average ?? 0,
            year: m.release_date
              ? new Date(m.release_date).getFullYear()
              : "N/A",
          }));
        }
      }
    } catch {
      // TMDb unavailable
    }
  }

  return NextResponse.json({
    matches: matchesCount,
    movies:  moviesCount,
    popular: popularMovies,
  });
}
