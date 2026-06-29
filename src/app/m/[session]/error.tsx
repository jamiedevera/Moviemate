"use client";

export default function Error({
  error,
}: {
  error: Error & { digest?: string };
}) {
  return (
    <div style={{ padding: 40, color: "#fff", background: "#111", minHeight: "100vh", fontFamily: "monospace" }}>
      <h1>Error in /m/[session]</h1>
      <pre style={{ whiteSpace: "pre-wrap", color: "#ff6b6b" }}>{error.message}</pre>
      <pre style={{ whiteSpace: "pre-wrap", color: "#888", fontSize: 12 }}>{error.stack}</pre>
    </div>
  );
}
