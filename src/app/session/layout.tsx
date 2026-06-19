import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "MovieMate — Start your session",
};

export default function SessionLayout({ children }: { children: React.ReactNode }) {
  return (
    <div style={{ minHeight: "100dvh", background: "#0a0a0f", display: "flex", flexDirection: "column" }}>
      {children}
    </div>
  );
}
