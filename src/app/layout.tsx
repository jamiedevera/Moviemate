import type { Metadata } from "next";
import { Playfair_Display, Inter } from "next/font/google";
import "./globals.css";

const inter = Inter({
  subsets: ["latin"],
  variable: "--font-inter",
  display: "swap",
});

const playfair = Playfair_Display({
  subsets: ["latin"],
  variable: "--font-playfair",
  display: "swap",
  weight: ["400", "500", "600", "700", "800", "900"],
  style: ["normal", "italic"],
});

export const metadata: Metadata = {
  title: "Moviemate — Premium Cinematic Matchmaking for Couples & Friends",
  description: "Find your next favorite movie together. Swipe, match instantly, and watch happier. Banish endless scrolling and enjoy a curated movie selection experience.",
  keywords: "movie matchmaking, movie swiping, couple movies, movie finder, premium cinema, what to watch",
  openGraph: {
    title: "Moviemate — Cinematic Matchmaking",
    description: "Find your next favorite movie together. Swipe, match instantly, and watch happier.",
    type: "website",
  }
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="en"
      className={`${inter.variable} ${playfair.variable} h-full antialiased`}
    >
      <body className="min-h-full flex flex-col bg-bg-dark text-primary-text selection:bg-primary-red selection:text-white">
        <div className="film-grain"></div>
        {children}
      </body>
    </html>
  );
}
