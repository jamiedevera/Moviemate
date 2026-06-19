"use client";

import { useState } from "react";
import type { TmdbMovie, TmdbRec } from "@/lib/tmdb";

interface Props {
  compatPercent:    number;
  sharedCount:      number;
  recommendedMovie: TmdbMovie;
  initialRecs:      TmdbRec[];
  extraRecs:        { title: string; poster: string; overview: string }[];
  personA:          TmdbMovie[];
  personB:          TmdbMovie[];
}

export default function MatchClient({
  compatPercent,
  sharedCount,
  recommendedMovie,
  initialRecs,
  extraRecs,
  personA,
  personB,
}: Props) {
  const [recs, setRecs]         = useState(initialRecs);
  const [queue, setQueue]       = useState(extraRecs);
  const [watched, setWatched]   = useState<{ title: string; poster: string }[]>([]);
  const [feedback, setFeedback] = useState("");

  function showFeedback(msg: string) {
    setFeedback(msg);
    setTimeout(() => setFeedback(""), 2500);
  }

  function handleSkip(idx: number) {
    if (queue.length > 0) {
      const [next, ...rest] = queue;
      setRecs((prev) => prev.map((r, i) => (i === idx ? (next as unknown as TmdbRec) : r)));
      setQueue(rest);
    } else {
      setRecs((prev) => prev.filter((_, i) => i !== idx));
    }
  }

  function handleSeen(idx: number) {
    const movie = recs[idx];
    setWatched((prev) => {
      if (prev.find((m) => m.title === movie.title)) return prev;
      return [...prev, { title: movie.title, poster: movie.poster }];
    });
    showFeedback("✓ Marked as seen!");
    handleSkip(idx);
  }

  return (
    <div style={{
      minHeight: "100vh",
      background: "#111827",
      color: "#f9fafb",
      fontFamily: "system-ui, sans-serif",
      padding: "0 0 60px",
    }}>
      {/* Feedback toast */}
      {feedback && (
        <div style={{
          position: "fixed", top: 20, right: 20, zIndex: 1000,
          background: "linear-gradient(135deg,#e50914,#ff4b4b)",
          color: "#fff", padding: "12px 16px", borderRadius: 8,
          fontWeight: 600, boxShadow: "0 4px 12px rgba(229,9,20,0.4)",
        }}>
          {feedback}
        </div>
      )}

      <div style={{ maxWidth: 1400, margin: "0 auto", padding: "40px 24px" }}>

        {/* Compatibility */}
        <h1 style={{ textAlign: "center", fontSize: "clamp(1.5rem,4vw,2.5rem)", fontWeight: 700 }}>
          Your Compatibility ❤️
        </h1>
        <div style={{
          textAlign: "center", fontSize: "clamp(4rem,15vw,8rem)",
          fontWeight: 900, color: "#e50914", lineHeight: 1, margin: "16px 0 32px",
        }}>
          {compatPercent}%
        </div>

        {/* Featured match */}
        <SectionTitle>You Both Picked</SectionTitle>
        <p style={{ textAlign: "center", color: "#9ca3af", marginBottom: 16 }}>
          {sharedCount > 0
            ? "You both chose the same movie! Perfect match 😳💘"
            : "No identical picks, but we found the closest match."}
        </p>

        <div style={{
          display: "flex", gap: 20, background: "rgba(255,255,255,0.05)",
          border: "1px solid rgba(255,255,255,0.1)", borderRadius: 12,
          padding: 20, marginBottom: 40, flexWrap: "wrap",
        }}>
          <img
            src={recommendedMovie.poster}
            alt={recommendedMovie.title}
            style={{ width: 140, borderRadius: 8, objectFit: "cover", flexShrink: 0 }}
          />
          <div style={{ flex: 1, minWidth: 200 }}>
            <h2 style={{ margin: "0 0 8px", fontSize: "1.4rem" }}>{recommendedMovie.title}</h2>
            <p style={{ color: "#9ca3af", lineHeight: 1.6, margin: 0 }}>{recommendedMovie.overview}</p>
          </div>
        </div>

        {/* Recommendations */}
        {recs.length > 0 && (
          <>
            <SectionTitle>New Movie Recommendations (For Both of You) 🍿</SectionTitle>
            <p style={{ color: "#9ca3af", textAlign: "center", marginBottom: 24 }}>
              Based on your top picks — all outside your current choices. Click <strong>Skip</strong> or <strong>Seen It</strong> to swap.
            </p>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill,minmax(180px,1fr))", gap: 16, marginBottom: 40 }}>
              {recs.map((m, i) => (
                <RecCard key={`${m.id}-${i}`} movie={m} onSkip={() => handleSkip(i)} onSeen={() => handleSeen(i)} />
              ))}
            </div>
          </>
        )}

        {recs.length === 0 && (
          <div style={{ textAlign: "center", padding: "60px 20px", border: "1px dashed rgba(255,255,255,0.1)", borderRadius: 12, marginBottom: 40 }}>
            <div style={{ fontSize: "3rem", marginBottom: 16 }}>🎬</div>
            <h3>No more recommendations</h3>
            <p style={{ color: "#9ca3af" }}>You've gone through all suggestions!</p>
          </div>
        )}

        {/* Person A picks */}
        <SectionTitle>Person A picked</SectionTitle>
        <MovieGrid movies={personA} />

        {/* Person B picks */}
        <SectionTitle style={{ marginTop: 32 }}>Person B picked</SectionTitle>
        <MovieGrid movies={personB} />

        {/* Watched history */}
        {watched.length > 0 && (
          <>
            <SectionTitle style={{ marginTop: 40 }}>Your Seen History</SectionTitle>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill,minmax(140px,1fr))", gap: 12 }}>
              {watched.map((m, i) => (
                <div key={i} style={{ textAlign: "center" }}>
                  <img src={m.poster} alt={m.title} style={{ width: "100%", borderRadius: 8 }} />
                  <div style={{ marginTop: 8, fontSize: "0.85rem", fontWeight: 600 }}>{m.title}</div>
                  <div style={{ fontSize: "0.75rem", color: "#6b7280" }}>Seen Today</div>
                </div>
              ))}
            </div>
          </>
        )}

        <div style={{ textAlign: "center", marginTop: 60 }}>
          <a
            href="/"
            onClick={(e) => {
              if (!confirm("This will reset the current session. Continue?")) e.preventDefault();
            }}
            style={{
              display: "inline-block", padding: "14px 32px",
              background: "#e50914", color: "#fff", borderRadius: 8,
              textDecoration: "none", fontWeight: 700, fontSize: "1rem",
            }}
          >
            Find More Movies
          </a>
        </div>
      </div>
    </div>
  );
}

function SectionTitle({ children, style }: { children: React.ReactNode; style?: React.CSSProperties }) {
  return (
    <h2 style={{
      fontSize: "1.2rem", fontWeight: 700, borderBottom: "1px solid rgba(255,255,255,0.1)",
      paddingBottom: 8, marginBottom: 16, ...style,
    }}>
      {children}
    </h2>
  );
}

function MovieGrid({ movies }: { movies: TmdbMovie[] }) {
  return (
    <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill,minmax(140px,1fr))", gap: 12, marginBottom: 16 }}>
      {movies.map((m) => (
        <div key={m.id} style={{ textAlign: "center" }}>
          <img src={m.poster} alt={m.title} style={{ width: "100%", borderRadius: 8, objectFit: "cover", aspectRatio: "2/3" }} />
          <div style={{ marginTop: 8, fontSize: "0.85rem", fontWeight: 600, lineHeight: 1.3 }}>{m.title}</div>
        </div>
      ))}
    </div>
  );
}

function RecCard({
  movie,
  onSkip,
  onSeen,
}: {
  movie: TmdbRec;
  onSkip: () => void;
  onSeen: () => void;
}) {
  return (
    <div style={{
      background: "rgba(255,255,255,0.04)",
      border: "1px solid rgba(255,255,255,0.08)",
      borderRadius: 12, padding: 12, display: "flex", flexDirection: "column",
    }}>
      <img src={movie.poster} alt={movie.title} style={{ width: "100%", borderRadius: 8, aspectRatio: "2/3", objectFit: "cover" }} />
      <div style={{ margin: "10px 0 4px", fontWeight: 700, fontSize: "0.9rem", lineHeight: 1.3 }}>{movie.title}</div>
      <div style={{ fontSize: "0.75rem", color: "#9ca3af", flex: 1, overflow: "hidden",
        display: "-webkit-box", WebkitLineClamp: 3, WebkitBoxOrient: "vertical" as const }}>
        {movie.overview}
      </div>
      <div style={{ display: "flex", flexDirection: "column", gap: 8, marginTop: 12 }}>
        <button onClick={onSkip} style={pillStyle("#374151")}>✕ Skip</button>
        <button onClick={onSeen} style={pillStyle("#065f46")}>✓ Seen It</button>
      </div>
    </div>
  );
}

function pillStyle(bg: string): React.CSSProperties {
  return {
    background: bg, color: "#fff", border: "none", borderRadius: 6,
    padding: "8px 12px", cursor: "pointer", fontWeight: 600, fontSize: "0.85rem",
  };
}
