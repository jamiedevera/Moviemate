"use client";

import { useState, useEffect, useRef, useCallback } from "react";
import { useParams, useRouter } from "next/navigation";
import { TMDB_GENRES } from "@/lib/tmdb";

const TMDB_KEY = process.env.NEXT_PUBLIC_TMDB_API_KEY!;
const TMDB_BASE = "https://api.themoviedb.org/3";

interface Movie {
  id: number;
  title: string;
  poster: string;
  year: string;
  rating: string;
  overview: string;
}

interface Props {
  who: "a" | "b";
}

function posterUrl(path: string | null): string {
  return path
    ? `https://image.tmdb.org/t/p/w300${path}`
    : "https://placehold.co/300x450/111827/666666?text=No+Poster";
}

export default function ChooseClient({ who }: Props) {
  const params = useParams();
  const router = useRouter();
  const session = params.session as string;
  const whoUpper = who.toUpperCase() as "A" | "B";

  const [selected, setSelected]       = useState<Movie[]>([]);
  const [movies, setMovies]           = useState<Movie[]>([]);
  const [page, setPage]               = useState(1);
  const [totalPages, setTotalPages]   = useState(1);
  const [loading, setLoading]         = useState(false);
  const [search, setSearch]           = useState("");
  const [searchResults, setSearchResults] = useState<Movie[]>([]);
  const [showSearch, setShowSearch]   = useState(false);
  const [genre, setGenre]             = useState("");
  const [year, setYear]               = useState("");
  const [feedback, setFeedback]       = useState("");
  const [submitting, setSubmitting]   = useState(false);
  const [waiting, setWaiting]         = useState(false);
  const [copied, setCopied]           = useState(false);
  const searchRef                     = useRef<HTMLDivElement>(null);

  const inviteUrl = typeof window !== "undefined"
    ? `${window.location.origin}/m/${session}`
    : "";

  function showFeedback(msg: string) {
    setFeedback(msg);
    setTimeout(() => setFeedback(""), 2500);
  }

  // Load popular/filtered movies
  const loadMovies = useCallback(async (p: number) => {
    setLoading(true);
    let url = `${TMDB_BASE}/discover/movie?api_key=${TMDB_KEY}&language=en-US&sort_by=popularity.desc&page=${p}&include_adult=false`;
    if (genre) url += `&with_genres=${genre}`;
    if (year)  url += `&primary_release_year=${year}`;

    try {
      const res  = await fetch(url);
      const data = await res.json();
      setTotalPages(Math.min(data.total_pages ?? 1, 500));
      setMovies(
        (data.results ?? []).map((m: Record<string, unknown>) => ({
          id:       m.id,
          title:    m.title,
          poster:   posterUrl(m.poster_path as string),
          year:     (m.release_date as string)?.slice(0, 4) ?? "?",
          rating:   m.vote_average ? Number(m.vote_average).toFixed(1) : "NR",
          overview: (m.overview as string) ?? "",
        }))
      );
    } catch {
      showFeedback("Failed to load movies.");
    } finally {
      setLoading(false);
    }
  }, [genre, year]);

  useEffect(() => { loadMovies(page); }, [page, loadMovies]);

  // Search
  useEffect(() => {
    if (search.length < 2) { setSearchResults([]); return; }
    const timer = setTimeout(async () => {
      const res  = await fetch(`${TMDB_BASE}/search/movie?api_key=${TMDB_KEY}&query=${encodeURIComponent(search)}`);
      const data = await res.json();
      setSearchResults(
        (data.results ?? []).map((m: Record<string, unknown>) => ({
          id:       m.id,
          title:    m.title,
          poster:   posterUrl(m.poster_path as string),
          year:     (m.release_date as string)?.slice(0, 4) ?? "?",
          rating:   m.vote_average ? Number(m.vote_average).toFixed(1) : "NR",
          overview: (m.overview as string) ?? "",
        }))
      );
    }, 300);
    return () => clearTimeout(timer);
  }, [search]);

  // Click outside search
  useEffect(() => {
    function handleClick(e: MouseEvent) {
      if (searchRef.current && !searchRef.current.contains(e.target as Node)) {
        setShowSearch(false);
      }
    }
    document.addEventListener("mousedown", handleClick);
    return () => document.removeEventListener("mousedown", handleClick);
  }, []);

  function addMovie(m: Movie) {
    if (selected.find((s) => s.id === m.id)) {
      showFeedback("Already selected!"); return;
    }
    if (selected.length >= 5) {
      showFeedback("Max 5 movies. Remove one first."); return;
    }
    setSelected((prev) => [...prev, m]);
    setSearch("");
    setShowSearch(false);
  }

  function removeMovie(id: number) {
    setSelected((prev) => prev.filter((m) => m.id !== id));
  }

  async function handleSubmit() {
    if (selected.length !== 5) {
      showFeedback("Select exactly 5 movies."); return;
    }
    setSubmitting(true);

    const form = new FormData();
    form.append("session", session);
    form.append("who", whoUpper);
    selected.forEach((m) => form.append("movies[]", String(m.id)));

    try {
      const res  = await fetch("/api/save", { method: "POST", body: form });
      const data = await res.json();

      if (!data.success) {
        showFeedback(data.error ?? "Failed to save.");
        setSubmitting(false);
        return;
      }

      if (data.bothDone) {
        router.push(`/m/${session}/match`);
      } else {
        setWaiting(true);
        // Poll for completion
        const interval = setInterval(async () => {
          try {
            const r = await fetch(`/api/status/${session}`);
            const d = await r.json();
            if (d.bothDone) {
              clearInterval(interval);
              router.push(`/m/${session}/match`);
            }
          } catch { /* keep polling */ }
        }, 3000);
      }
    } catch {
      showFeedback("Network error. Please try again.");
      setSubmitting(false);
    }
  }

  async function copyInvite() {
    try { await navigator.clipboard.writeText(inviteUrl); }
    catch { /* fallback */ }
    setCopied(true);
    setTimeout(() => setCopied(false), 2500);
  }

  if (waiting) {
    return (
      <div style={overlayStyle}>
        <div style={{ textAlign: "center" }}>
          <div style={{ fontSize: "3rem", marginBottom: 16 }}>🍿</div>
          <h2 style={{ marginBottom: 8 }}>Picks saved!</h2>
          <p style={{ color: "#9ca3af" }}>Waiting for your MovieMate to finish picking…</p>
        </div>
      </div>
    );
  }

  return (
    <div style={{ minHeight: "100vh", background: "#111827", color: "#f9fafb", fontFamily: "system-ui,sans-serif" }}>
      {feedback && (
        <div style={{ position: "fixed", top: 20, right: 20, zIndex: 1000,
          background: "#e50914", color: "#fff", padding: "12px 16px",
          borderRadius: 8, fontWeight: 600 }}>
          {feedback}
        </div>
      )}

      <div style={{ maxWidth: 1200, margin: "0 auto", padding: "32px 24px" }}>
        <h1 style={{ fontSize: "1.75rem", fontWeight: 700, marginBottom: 24 }}>
          Select Your Movies 🎬
        </h1>

        {/* Invite banner — only for Person A */}
        {who === "a" && (
          <div style={{
            background: "rgba(229,9,20,0.08)", border: "1px solid rgba(229,9,20,0.3)",
            borderRadius: 12, padding: "16px 20px", marginBottom: 24,
            display: "flex", alignItems: "center", gap: 16, flexWrap: "wrap",
          }}>
            <div style={{ flex: 1, minWidth: 200 }}>
              <strong>🔗 Invite your partner:</strong> Share this link!
              <div style={{ fontSize: "0.8rem", color: "#9ca3af", marginTop: 4, wordBreak: "break-all" }}>
                {inviteUrl}
              </div>
            </div>
            <button onClick={copyInvite} style={btnStyle("#e50914")}>
              {copied ? "✓ Copied!" : "Copy Link"}
            </button>
          </div>
        )}

        <div style={{ display: "flex", gap: 24, alignItems: "flex-start", flexWrap: "wrap" }}>
          {/* Main content */}
          <div style={{ flex: 1, minWidth: 300 }}>
            {/* Search */}
            <div ref={searchRef} style={{ position: "relative", marginBottom: 16 }}>
              <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                <input
                  style={inputStyle}
                  placeholder="Search any movie..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  onFocus={() => setShowSearch(true)}
                />
                <select
                  style={{ ...inputStyle, flex: "0 0 auto", width: "auto" }}
                  value={genre}
                  onChange={(e) => setGenre(e.target.value)}
                >
                  <option value="">Genre</option>
                  {Object.entries(TMDB_GENRES).map(([id, name]) => (
                    <option key={id} value={id}>{name}</option>
                  ))}
                </select>
                <input
                  style={{ ...inputStyle, flex: "0 0 auto", width: 90 }}
                  placeholder="Year"
                  type="number"
                  value={year}
                  onChange={(e) => setYear(e.target.value)}
                />
                <button style={btnStyle("#374151")} onClick={() => { setPage(1); loadMovies(1); }}>
                  Go
                </button>
              </div>

              {/* Search dropdown */}
              {showSearch && searchResults.length > 0 && (
                <div style={{
                  position: "absolute", top: "100%", left: 0, right: 0, zIndex: 100,
                  background: "#1f2937", border: "1px solid rgba(255,255,255,0.1)",
                  borderRadius: 8, maxHeight: 360, overflowY: "auto", marginTop: 4,
                }}>
                  {searchResults.map((m) => (
                    <div
                      key={m.id}
                      onClick={() => addMovie(m)}
                      style={{
                        display: "flex", gap: 12, padding: "10px 12px",
                        cursor: "pointer", borderBottom: "1px solid rgba(255,255,255,0.06)",
                      }}
                      onMouseEnter={(e) => (e.currentTarget.style.background = "rgba(255,255,255,0.05)")}
                      onMouseLeave={(e) => (e.currentTarget.style.background = "")}
                    >
                      <img src={m.poster} alt={m.title} style={{ width: 36, height: 54, objectFit: "cover", borderRadius: 4 }} />
                      <div>
                        <div style={{ fontWeight: 600, fontSize: "0.9rem" }}>{m.title}</div>
                        <div style={{ fontSize: "0.75rem", color: "#9ca3af" }}>{m.year} · ★ {m.rating}</div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>

            {/* Movie grid */}
            {loading ? (
              <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill,minmax(150px,1fr))", gap: 12 }}>
                {Array.from({ length: 10 }).map((_, i) => (
                  <div key={i} style={{ background: "rgba(255,255,255,0.05)", borderRadius: 8, aspectRatio: "2/3", animation: "pulse 1.5s infinite" }} />
                ))}
              </div>
            ) : (
              <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill,minmax(150px,1fr))", gap: 12 }}>
                {movies.map((m) => {
                  const isSelected = selected.some((s) => s.id === m.id);
                  return (
                    <div
                      key={m.id}
                      onClick={() => addMovie(m)}
                      style={{
                        cursor: "pointer", borderRadius: 8, overflow: "hidden", position: "relative",
                        border: isSelected ? "2px solid #e50914" : "2px solid transparent",
                        transition: "border-color 0.2s",
                      }}
                    >
                      <img src={m.poster} alt={m.title} style={{ width: "100%", display: "block", aspectRatio: "2/3", objectFit: "cover" }} />
                      {isSelected && (
                        <div style={{
                          position: "absolute", top: 6, right: 6,
                          background: "#e50914", color: "#fff", borderRadius: "50%",
                          width: 24, height: 24, display: "flex", alignItems: "center", justifyContent: "center",
                          fontSize: "0.8rem", fontWeight: 700,
                        }}>✓</div>
                      )}
                      <div style={{ padding: "8px 6px", background: "rgba(0,0,0,0.8)" }}>
                        <div style={{ fontSize: "0.78rem", fontWeight: 600, lineHeight: 1.3 }}>{m.title}</div>
                        <div style={{ fontSize: "0.7rem", color: "#9ca3af", marginTop: 2 }}>
                          {m.year} · ★{m.rating}
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}

            {/* Pagination */}
            {totalPages > 1 && (
              <div style={{ display: "flex", gap: 8, justifyContent: "center", marginTop: 24, flexWrap: "wrap" }}>
                {page > 1 && (
                  <button style={btnStyle("#374151")} onClick={() => setPage((p) => p - 1)}>« Prev</button>
                )}
                {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
                  const start = Math.max(1, Math.min(page - 2, totalPages - 4));
                  const p = start + i;
                  return (
                    <button
                      key={p}
                      style={btnStyle(p === page ? "#e50914" : "#374151")}
                      onClick={() => setPage(p)}
                    >
                      {p}
                    </button>
                  );
                })}
                {page < totalPages && (
                  <button style={btnStyle("#374151")} onClick={() => setPage((p) => p + 1)}>Next »</button>
                )}
              </div>
            )}
          </div>

          {/* Sidebar */}
          {selected.length > 0 && (
            <div style={{
              width: 260, flexShrink: 0, background: "rgba(255,255,255,0.04)",
              border: "1px solid rgba(255,255,255,0.1)", borderRadius: 12, padding: 16,
              position: "sticky", top: 24,
            }}>
              <h3 style={{ margin: "0 0 12px", fontSize: "1rem" }}>Your Top 5 Picks</h3>

              {/* Progress pills */}
              <div style={{ display: "flex", gap: 6, marginBottom: 16 }}>
                {Array.from({ length: 5 }).map((_, i) => (
                  <div key={i} style={{
                    flex: 1, height: 6, borderRadius: 3,
                    background: i < selected.length ? "#e50914" : "rgba(255,255,255,0.15)",
                    transition: "background 0.2s",
                  }} />
                ))}
              </div>

              {/* Selected list */}
              <div style={{ display: "flex", flexDirection: "column", gap: 10, marginBottom: 16 }}>
                {selected.map((m) => (
                  <div key={m.id} style={{ display: "flex", gap: 10, alignItems: "center" }}>
                    <img src={m.poster} alt={m.title} style={{ width: 40, height: 60, objectFit: "cover", borderRadius: 4, flexShrink: 0 }} />
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ fontSize: "0.82rem", fontWeight: 600, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{m.title}</div>
                      <div style={{ fontSize: "0.72rem", color: "#9ca3af" }}>{m.year}</div>
                    </div>
                    <button
                      onClick={() => removeMovie(m.id)}
                      style={{ background: "none", border: "none", color: "#9ca3af", cursor: "pointer", fontSize: "1rem", flexShrink: 0 }}
                    >✕</button>
                  </div>
                ))}
              </div>

              <button
                onClick={handleSubmit}
                disabled={selected.length !== 5 || submitting}
                style={{
                  ...btnStyle("#e50914"),
                  width: "100%", opacity: selected.length !== 5 ? 0.4 : 1,
                  cursor: selected.length !== 5 ? "not-allowed" : "pointer",
                }}
              >
                {submitting ? "Saving…" : "Submit Picks"}
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

const inputStyle: React.CSSProperties = {
  flex: 1, padding: "10px 14px", background: "rgba(255,255,255,0.06)",
  border: "1px solid rgba(255,255,255,0.12)", borderRadius: 8,
  color: "#f9fafb", fontSize: "0.95rem", fontFamily: "inherit", outline: "none",
  minWidth: 0,
};

function btnStyle(bg: string): React.CSSProperties {
  return {
    padding: "10px 16px", background: bg, color: "#fff", border: "none",
    borderRadius: 8, cursor: "pointer", fontWeight: 600, fontSize: "0.9rem",
    fontFamily: "inherit", whiteSpace: "nowrap",
  };
}

const overlayStyle: React.CSSProperties = {
  position: "fixed", inset: 0, background: "rgba(0,0,0,0.85)",
  display: "flex", alignItems: "center", justifyContent: "center",
  zIndex: 500, color: "#f9fafb", fontFamily: "system-ui,sans-serif",
};
