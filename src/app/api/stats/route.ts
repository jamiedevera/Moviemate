import { NextResponse } from "next/server";

export const revalidate = 300; // cache for 5 minutes

export async function GET() {
  let matchesCount = 0;
  let satisfactionRate = 0;
  let moviesCount = 0;

  // --- Fetch match/session count from PHP backend ---
  try {
    const baseUrl =
      process.env.NEXT_PUBLIC_BASE_URL ||
      (process.env.VERCEL_URL ? `https://${process.env.VERCEL_URL}` : "http://localhost:3000");

    const res = await fetch(`${baseUrl}/api/db-stats.php`, {
      next: { revalidate: 300 },
    });

    if (res.ok) {
      const data = await res.json();
      matchesCount = data.matches ?? 0;
      satisfactionRate = data.satisfaction ?? 0;
    }
  } catch {
    // DB unavailable – use 0 as fallback, frontend will show a placeholder
  }

  // --- Fetch total available movies from TMDB ---
  try {
    const TMDB_API_KEY = process.env.TMDB_API_KEY;
    if (TMDB_API_KEY) {
      const tmdbRes = await fetch(
        `https://api.themoviedb.org/3/movie/popular?api_key=${TMDB_API_KEY}&page=1`,
        { next: { revalidate: 3600 } }
      );
      if (tmdbRes.ok) {
        const tmdbData = await tmdbRes.json();
        moviesCount = tmdbData.total_results ?? 0;
      }
    }
  } catch {
    // TMDB unavailable
  }

  return NextResponse.json({
    movies: moviesCount,
    matches: matchesCount,
    satisfaction: satisfactionRate,
  });
}
