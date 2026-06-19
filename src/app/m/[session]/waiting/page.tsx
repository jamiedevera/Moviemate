"use client";

import { useEffect, useRef } from "react";
import { useParams, useRouter } from "next/navigation";

export default function WaitingPage() {
  const params  = useParams();
  const router  = useRouter();
  const session = params.session as string;
  const timer   = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    async function poll() {
      try {
        const res  = await fetch(`/api/status/${session}`, { cache: "no-store" });
        const data = await res.json();
        if (data?.bothDone) {
          if (timer.current) clearInterval(timer.current);
          router.replace(`/m/${session}/match`);
        }
      } catch { /* keep polling */ }
    }
    poll();
    timer.current = setInterval(poll, 3000);
    return () => { if (timer.current) clearInterval(timer.current); };
  }, [session, router]);

  return (
    <div style={{
      minHeight: "100vh", background: "#111827", color: "#f9fafb",
      display: "flex", alignItems: "center", justifyContent: "center",
      fontFamily: "system-ui,sans-serif", padding: 24,
    }}>
      <div style={{ textAlign: "center", maxWidth: 420 }}>
        <div style={{ fontSize: "2.5rem", marginBottom: 16 }}>⏳</div>
        <h2 style={{ marginBottom: 8 }}>Not Ready Yet</h2>
        <p style={{ color: "#9ca3af" }}>
          Both people need to finish choosing their movies before viewing the results.
        </p>
      </div>
    </div>
  );
}
