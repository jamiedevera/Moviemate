import { NextResponse } from "next/server";
import fs from "fs";
import path from "path";

export const revalidate = 300; // cache for 5 minutes

export async function GET() {
  let matchesCount = 0;
  let satisfactionRate = 98; // Default fallback
  let moviesCount = 5000; // Default fallback
  let popularMovies: any[] = [];

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
      satisfactionRate = data.satisfaction ?? 98;
    }
  } catch {
    // DB unavailable – use defaults
  }

  // --- Resolve TMDB API Key (check process.env first, fallback to env.ini for local dev) ---
  let tmdbKey = process.env.TMDB_API_KEY;
  if (!tmdbKey) {
    try {
      const envPath = path.join(process.cwd(), "env.ini");
      if (fs.existsSync(envPath)) {
        const content = fs.readFileSync(envPath, "utf-8");
        const match = content.match(/TMDB_KEY_B64\s*=\s*["']?([^"'\s]+)["']?/);
        if (match && match[1]) {
          tmdbKey = Buffer.from(match[1], "base64").toString("utf-8").trim();
        }
      }
    } catch {
      // Ignore
    }
  }

  // --- Fetch total available movies & popular movies from TMDB ---
  if (tmdbKey) {
    try {
      const tmdbRes = await fetch(
        `https://api.themoviedb.org/3/movie/popular?api_key=${tmdbKey}&page=1`,
        { next: { revalidate: 3600 } }
      );
      if (tmdbRes.ok) {
        const tmdbData = await tmdbRes.json();
        moviesCount = tmdbData.total_results ?? 5000;
        
        if (tmdbData.results && Array.isArray(tmdbData.results)) {
          popularMovies = tmdbData.results.slice(0, 10).map((m: any) => ({
            id: m.id,
            title: m.title,
            poster: m.poster_path ? `https://image.tmdb.org/t/p/w342${m.poster_path}` : null,
            rating: m.vote_average ?? 0,
            year: m.release_date ? new Date(m.release_date).getFullYear() : "N/A",
          }));
        }
      }
    } catch {
      // TMDB unavailable
    }
  }

  return NextResponse.json({
    movies: moviesCount,
    matches: matchesCount,
    satisfaction: satisfactionRate,
    popular: popularMovies,
  });
}
