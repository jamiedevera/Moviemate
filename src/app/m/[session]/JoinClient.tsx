"use client";

import { useState, useRef, useEffect } from "react";
import { useRouter } from "next/navigation";

interface Props {
  session:  string;
  hostName: string;
}

export default function JoinClient({ session, hostName }: Props) {
  const router = useRouter();
  const [name, setName]         = useState("");
  const [screen, setScreen]     = useState<"ticket" | "arrival">("ticket");
  const [flashing, setFlashing] = useState(false);
  const [busy, setBusy]         = useState(false);
  const inputRef                = useRef<HTMLInputElement>(null);

  useEffect(() => { inputRef.current?.focus(); }, []);

  async function handleJoin() {
    const trimmed = name.trim();
    if (!trimmed) return;
    setBusy(true);

    try { localStorage.setItem("mm_name", trimmed); } catch { /* ignore */ }

    try {
      await fetch(`/api/join-b/${session}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name: trimmed }),
      });
    } catch { /* ignore — best effort */ }

    setFlashing(true);
    await sleep(250);
    setScreen("arrival");
    setFlashing(false);
  }

  function handleStart() {
    const params = new URLSearchParams({ name: name.trim() });
    router.push(`/m/${session}/b?${params.toString()}`);
  }

  return (
    <div style={shellStyle}>
      <div style={grainStyle} />
      <div style={{ position: "fixed", inset: 0, background: "#0a0a0f", opacity: flashing ? 1 : 0,
        pointerEvents: "none", transition: "opacity .25s ease", zIndex: 200 }} />

      <div style={{ position: "relative", zIndex: 1, width: "100%", maxWidth: 440 }}>
        {screen === "ticket" ? (
          <div style={stepStyle}>
            <div style={ticketStyle}>
              <div style={ticketStripe} />
              <span style={{ fontSize: "2.2rem", display: "block", marginBottom: 12 }}>🎟️</span>
              <p style={eyebrowStyle}>Movie Ticket</p>
              <h1 style={headingStyle}>{hostName} invited you to a MovieMate screening.</h1>
              <div style={tearStyle} />
              <p style={stubStyle}>One admit — present this ticket at the door</p>
            </div>

            <input
              ref={inputRef}
              style={inputStyle}
              placeholder="What's your name?"
              value={name}
              onChange={(e) => setName(e.target.value)}
              maxLength={40}
              onKeyDown={(e) => e.key === "Enter" && handleJoin()}
            />

            <button
              style={{ ...btnStyle, opacity: !name.trim() || busy ? 0.4 : 1 }}
              disabled={!name.trim() || busy}
              onClick={handleJoin}
            >
              Join {hostName}&apos;s Screening
            </button>
          </div>
        ) : (
          <div style={stepStyle}>
            <span style={{ fontSize: "2.2rem" }}>🎬</span>
            <h1 style={headingStyle}>Both MovieMates have arrived.</h1>
            <p style={subStyle}>The theater is ready. Time to pick your movies.</p>

            <div style={roomStyle}>
              <Seat label={hostName} />
              <div style={{ fontSize: "1.1rem" }}>❤️</div>
              <Seat label={name} animate />
            </div>

            <button style={btnStyle} onClick={handleStart}>
              Start Choosing Movies
            </button>
          </div>
        )}
      </div>
    </div>
  );
}

function Seat({ label, animate }: { label: string; animate?: boolean }) {
  return (
    <div style={{
      flex: 1, display: "flex", flexDirection: "column", alignItems: "center", gap: 6,
      padding: "20px 16px", borderRadius: 12,
      background: "rgba(229,9,20,0.08)", border: "1px solid rgba(229,9,20,0.3)",
      animation: animate ? "seatPop .4s cubic-bezier(.22,1,.36,1) .4s both" : undefined,
    }}>
      <div style={{ fontSize: "2rem" }}>🍿</div>
      <div style={{ fontSize: "0.9rem", fontWeight: 500, color: "rgba(255,255,255,0.85)" }}>{label}</div>
      <div style={{ fontSize: "0.7rem", color: "rgba(255,255,255,0.35)" }}>Seated</div>
      <style jsx>{`
        @keyframes seatPop { from { transform: scale(.92); opacity: 0 } to { transform: scale(1); opacity: 1 } }
      `}</style>
    </div>
  );
}

function sleep(ms: number) { return new Promise((r) => setTimeout(r, ms)); }

const shellStyle: React.CSSProperties = {
  position: "relative", minHeight: "100dvh", background: "#0a0a0f",
  display: "flex", alignItems: "center", justifyContent: "center",
  fontFamily: "system-ui,-apple-system,sans-serif", padding: "2rem 1rem",
  color: "#f5f5f5", overflow: "hidden",
};

const grainStyle: React.CSSProperties = {
  position: "fixed", inset: 0, pointerEvents: "none", opacity: 0.035,
  backgroundImage: "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E\")",
  backgroundSize: "200px 200px",
};

const stepStyle: React.CSSProperties = {
  display: "flex", flexDirection: "column", alignItems: "center", gap: 16, textAlign: "center",
};

const ticketStyle: React.CSSProperties = {
  background: "linear-gradient(135deg,#1a1a24,#13131c)", border: "1px solid rgba(255,255,255,0.1)",
  borderRadius: 16, padding: "2rem", width: "100%", position: "relative", overflow: "hidden",
};

const ticketStripe: React.CSSProperties = {
  position: "absolute", top: 0, left: 0, right: 0, height: 3,
  background: "linear-gradient(90deg,#e50914,#ff6b6b,#e50914)",
};

const eyebrowStyle: React.CSSProperties = {
  fontSize: "0.65rem", fontWeight: 700, letterSpacing: "0.2em", textTransform: "uppercase",
  color: "#e50914", marginBottom: 8,
};

const headingStyle: React.CSSProperties = {
  fontSize: "1.4rem", fontWeight: 700, letterSpacing: "-0.02em", lineHeight: 1.3, margin: 0,
};

const subStyle: React.CSSProperties = {
  fontSize: "0.95rem", color: "rgba(255,255,255,0.5)", lineHeight: 1.6, margin: 0,
};

const tearStyle: React.CSSProperties = {
  width: "100%", height: 1, margin: "1.25rem 0",
  background: "repeating-linear-gradient(90deg,rgba(255,255,255,0.12) 0px,rgba(255,255,255,0.12) 6px,transparent 6px,transparent 12px)",
};

const stubStyle: React.CSSProperties = {
  fontSize: "0.7rem", color: "rgba(255,255,255,0.25)", letterSpacing: "0.08em", textTransform: "uppercase", margin: 0,
};

const inputStyle: React.CSSProperties = {
  width: "100%", boxSizing: "border-box", padding: "0.875rem 1.125rem",
  background: "rgba(255,255,255,0.06)", border: "1px solid rgba(255,255,255,0.12)",
  borderRadius: 10, color: "#f5f5f5", fontSize: "1rem", fontFamily: "inherit", outline: "none",
};

const btnStyle: React.CSSProperties = {
  width: "100%", padding: "0.9rem 1.5rem", background: "#e50914", color: "#fff",
  fontSize: "0.95rem", fontWeight: 700, fontFamily: "inherit", border: "none",
  borderRadius: 10, cursor: "pointer",
};

const roomStyle: React.CSSProperties = {
  display: "flex", alignItems: "center", gap: 20, width: "100%", margin: "8px 0",
};
