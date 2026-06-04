"use client";

import { useEffect, useState, useRef, useCallback } from "react";
import { motion, AnimatePresence } from "framer-motion";
import {
  Ticket,
  Film,
  Popcorn,
  Heart,
  ChevronRight,
  Play,
  Star,
  Users,
  X,
  Check,
  Clapperboard,
  Sparkle,
} from "lucide-react";

interface Particle {
  id: number;
  size: number;
  left: string;
  delay: string;
  duration: string;
}

interface MovieItem {
  id: number;
  title: string;
  poster: string | null;
  rating: number;
  year: number | string;
}

interface Stats {
  movies: number;
  matches: number;
  satisfaction: number;
  popular?: MovieItem[];
}

const fallbackPopularMovies: MovieItem[] = [
  { id: 1, title: "Dune: Part Two", poster: "https://image.tmdb.org/t/p/w342/czemb5hm1a88L924u6iU1X54aaR.jpg", rating: 8.3, year: 2024 },
  { id: 2, title: "Deadpool & Wolverine", poster: "https://image.tmdb.org/t/p/w342/8cdWjvZ1ZUD28750yPVmNf5Rihg.jpg", rating: 7.7, year: 2024 },
  { id: 3, title: "Inside Out 2", poster: "https://image.tmdb.org/t/p/w342/vpnVM9B6mFJ44vY78QC4ok658oD.jpg", rating: 7.6, year: 2024 },
  { id: 4, title: "Oppenheimer", poster: "https://image.tmdb.org/t/p/w342/8Gxv2wS0EH1SliPWwBg7xdZvRQI.jpg", rating: 8.1, year: 2023 },
  { id: 5, title: "Gladiator II", poster: "https://image.tmdb.org/t/p/w342/2cxh2wG4ST1FhZgDY04XlDAj1Yk.jpg", rating: 6.9, year: 2024 },
  { id: 6, title: "Interstellar", poster: "https://image.tmdb.org/t/p/w342/gEU2Qv4wU6JvKWfvwaUR85XaZOF.jpg", rating: 8.4, year: 2014 }
];


// Animated counter hook
function useCountUp(target: number, duration = 2000, start = false) {
  const [count, setCount] = useState(0);
  useEffect(() => {
    if (!start || target === 0) return;
    let startTime: number | null = null;
    const step = (timestamp: number) => {
      if (!startTime) startTime = timestamp;
      const progress = Math.min((timestamp - startTime) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3); // ease out cubic
      setCount(Math.floor(eased * target));
      if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  }, [target, duration, start]);
  return count;
}

function StatItem({
  icon,
  value,
  label,
  suffix = "+",
  loading,
}: {
  icon: React.ReactNode;
  value: number;
  label: string;
  suffix?: string;
  loading: boolean;
}) {
  const [inView, setInView] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const count = useCountUp(value, 1800, inView && !loading);

  useEffect(() => {
    const obs = new IntersectionObserver(
      ([entry]) => { if (entry.isIntersecting) setInView(true); },
      { threshold: 0.3 }
    );
    if (ref.current) obs.observe(ref.current);
    return () => obs.disconnect();
  }, []);

  return (
    <div ref={ref} className="flex flex-col items-center text-center gap-3">
      <div className="text-gold-accent">{icon}</div>
      <div className="text-4xl md:text-5xl font-extrabold text-white tracking-wide font-playfair">
        {loading ? (
          <span className="animate-pulse text-secondary-text text-2xl">—</span>
        ) : (
          <>
            {value >= 1000 ? `${(count / 1000).toFixed(count >= 1000 ? 0 : 1)}K` : count}
            {suffix}
          </>
        )}
      </div>
      <div className="text-xs uppercase tracking-widest text-secondary-text font-semibold font-inter">
        {label}
      </div>
    </div>
  );
}

export default function Home() {
  const [scrolled, setScrolled] = useState(false);
  const [authModalOpen, setAuthModalOpen] = useState(false);
  const [authMode, setAuthMode] = useState<"login" | "signup">("login");
  const [particles, setParticles] = useState<Particle[]>([]);
  const [formData, setFormData] = useState({ username: "", email: "", password: "" });
  const [authError, setAuthError] = useState("");
  const [authSuccess, setAuthSuccess] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const [stats, setStats] = useState<Stats | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);

  // Fetch live stats
  useEffect(() => {
    fetch("/api/stats")
      .then((r) => r.json())
      .then((data) => {
        setStats(data);
        setStatsLoading(false);
      })
      .catch(() => {
        // On error, show zeroed stats
        setStats({ movies: 0, matches: 0, satisfaction: 0 });
        setStatsLoading(false);
      });
  }, []);

  // Scroll listener
  useEffect(() => {
    const handleScroll = () => setScrolled(window.scrollY > 40);
    window.addEventListener("scroll", handleScroll);
    return () => window.removeEventListener("scroll", handleScroll);
  }, []);

  // Hydration-safe particles
  useEffect(() => {
    setParticles(
      Array.from({ length: 30 }, (_, i) => ({
        id: i,
        size: Math.random() * 3 + 1,
        left: `${Math.random() * 100}%`,
        delay: `${Math.random() * 20}s`,
        duration: `${Math.random() * 25 + 20}s`,
      }))
    );
  }, []);

  const handleAuthSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setAuthError("");
    setAuthSuccess("");
    setIsLoading(true);

    const body = new FormData();
    body.append("action", authMode);
    if (authMode === "signup") body.append("username", formData.username);
    body.append("email", formData.email);
    body.append("password", formData.password);

    try {
      const res = await fetch("/api/auth", { method: "POST", body });
      const data = await res.json();
      if (data.success) {
        setAuthSuccess(
          authMode === "login"
            ? "Welcome back! Redirecting..."
            : "Account created! Redirecting..."
        );
        setTimeout(() => window.location.reload(), 1500);
      } else {
        setAuthError(data.error || "Authentication failed. Please try again.");
      }
    } catch {
      setAuthError("Network error. Please try again.");
    } finally {
      setIsLoading(false);
    }
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const genres = [
    { name: "ACTION", tag: "Adrenaline Rush", img: "/images/genre_action.png" },
    { name: "SCI-FI", tag: "Beyond Imagination", img: "/images/genre_scifi.png" },
    { name: "HORROR", tag: "Face Your Fears", img: "/images/genre_horror.png" },
    { name: "COMEDY", tag: "Laugh Together", img: "/images/genre_comedy.png" },
    { name: "ROMANCE", tag: "Feel the Love", img: "/images/genre_romance.png" },
    { name: "THRILLER", tag: "Edge of Your Seat", img: "/images/genre_thriller.png" },
  ];

  const steps = [
    { icon: <Ticket className="w-7 h-7" />, emoji: "🎟", title: "Create Session", desc: "Start a movie session and get a unique link." },
    { icon: <Popcorn className="w-7 h-7" />, emoji: "🍿", title: "Invite Partner", desc: "Share the link and invite your movie partner." },
    { icon: <Clapperboard className="w-7 h-7" />, emoji: "🎬", title: "Start Swiping", desc: "Browse movies together and share your picks." },
    { icon: <Heart className="w-7 h-7 fill-current" />, emoji: "❤️", title: "Match Movies", desc: "Get matched and find your perfect movie to watch." },
  ];

  const popularList = (stats?.popular && stats.popular.length > 0)
    ? stats.popular
    : fallbackPopularMovies;

  // Duplicate 4x for seamless infinite loop
  const filmstripList = [...popularList, ...popularList, ...popularList, ...popularList];

  // Film strip drag-to-scroll
  const filmstripRef = useRef<HTMLDivElement>(null);
  const trackRef = useRef<HTMLDivElement>(null);
  const [isDragging, setIsDragging] = useState(false);
  const dragStartX = useRef(0);
  const scrollStartX = useRef(0);

  const handleDragStart = useCallback((e: React.MouseEvent) => {
    if (!trackRef.current) return;
    setIsDragging(true);
    dragStartX.current = e.clientX;
    // Get current CSS transform translateX
    const style = window.getComputedStyle(trackRef.current);
    const matrix = new DOMMatrix(style.transform);
    scrollStartX.current = matrix.m41; // translateX value
  }, []);

  const handleDragMove = useCallback((e: React.MouseEvent) => {
    if (!isDragging || !trackRef.current) return;
    const dx = e.clientX - dragStartX.current;
    trackRef.current.style.transform = `translateX(${scrollStartX.current + dx}px)`;
  }, [isDragging]);

  const handleDragEnd = useCallback(() => {
    if (!isDragging) return;
    setIsDragging(false);
    // Let the CSS animation resume from current position — reset inline style
    if (trackRef.current) {
      trackRef.current.style.transform = "";
    }
  }, [isDragging]);

  return (
    <div className="relative min-h-screen bg-bg-dark text-primary-text font-inter overflow-x-hidden">

      {/* Film grain */}
      <div className="film-grain" />

      {/* ── NAVBAR ── */}
      <nav
        className={`fixed top-0 left-0 w-full z-50 transition-all duration-400 ${
          scrolled
            ? "bg-bg-dark/90 backdrop-blur-md border-b border-border-light py-3 shadow-2xl"
            : "bg-transparent py-5"
        }`}
      >
        <div className="max-w-7xl mx-auto px-6 flex items-center justify-between">
          <a href="#" className="flex items-center gap-2 select-none">
            <span className="text-primary-red text-xl">🎬</span>
            <span className="font-playfair font-bold text-xl tracking-wider text-white uppercase">
              Moviemate
            </span>
          </a>

          <div className="hidden md:flex items-center gap-8 text-sm font-medium text-secondary-text">
            {["Home", "How It Works", "Genres", "About", "FAQ"].map((item) => (
              <a
                key={item}
                href={item === "Home" ? "#" : `#${item.toLowerCase().replace(/\s+/g, "-")}`}
                className="hover:text-white transition-colors duration-200 relative group"
              >
                {item}
                <span className="absolute -bottom-0.5 left-0 w-0 h-px bg-primary-red group-hover:w-full transition-all duration-300" />
              </a>
            ))}
          </div>

          <div className="flex items-center gap-3">
            <button
              onClick={() => { setAuthMode("login"); setAuthModalOpen(true); }}
              className="px-5 py-2 text-sm font-semibold text-primary-text hover:text-white border border-transparent hover:border-border-light rounded-md transition-all duration-200 cursor-pointer"
            >
              Log In
            </button>
            <button
              onClick={() => { setAuthMode("signup"); setAuthModalOpen(true); }}
              className="px-5 py-2 text-sm font-bold text-white bg-primary-red hover:bg-red-700 rounded-md shadow-lg shadow-red-950/40 active:scale-95 transition-all duration-200 cursor-pointer"
            >
              Sign Up
            </button>
          </div>
        </div>
      </nav>

      {/* ── HERO SECTION ── */}
      <section className="relative min-h-screen flex items-center overflow-hidden">

        {/* Full-bleed cinematic background */}
        <div className="absolute inset-0 z-0">
          <img
            src="/images/cinematic_hero_bg.png"
            alt="Cinematic theater background"
            className="w-full h-full object-cover object-center select-none"
          />
          {/* Layered overlays for depth */}
          <div className="absolute inset-0 bg-gradient-to-r from-black/95 via-black/60 to-black/80" />
          <div className="absolute inset-0 bg-gradient-to-t from-bg-dark via-transparent to-bg-dark/40" />
          {/* Golden projector glow on the left */}
          <div className="absolute top-0 left-0 w-1/2 h-full bg-[radial-gradient(ellipse_at_left_center,rgba(212,175,55,0.07)_0%,transparent_60%)]" />
        </div>

        {/* Floating dust particles */}
        <div className="dust-container">
          {particles.map((p) => (
            <div
              key={p.id}
              className="dust-particle"
              style={{ width: p.size, height: p.size, left: p.left, animationDelay: p.delay, animationDuration: p.duration }}
            />
          ))}
        </div>

        {/* Hero content */}
        <div className="relative z-10 max-w-7xl mx-auto px-6 w-full pt-20">
          <div className="flex items-center justify-center">

            {/* Main copy — centered */}
            <div className="flex-1 max-w-2xl text-center md:text-left">
              {/* Badge */}
              <motion.div
                initial={{ opacity: 0, y: -16 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5 }}
                className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-card-dark/80 border border-border-light text-xs font-semibold tracking-widest text-gold-accent uppercase mb-6 backdrop-blur-sm"
              >
                <Sparkle className="w-3 h-3 fill-current" />
                Find Your Next Favorite Movie
              </motion.div>

              {/* Main Title */}
              <motion.div
                initial={{ opacity: 0, y: 30 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.8, delay: 0.1 }}
              >
                <h1 className="font-playfair leading-none mb-6">
                  {/* MOVIE in white */}
                  <span className="block text-[clamp(4rem,12vw,8rem)] font-black text-white tracking-tight cinematic-text-shadow">
                    MOVIE
                  </span>
                  {/* MATE in red */}
                  <span className="block text-[clamp(4rem,12vw,8rem)] font-black text-primary-red tracking-tight leading-none" style={{ marginTop: "-0.15em" }}>
                    MATE
                  </span>
                </h1>
              </motion.div>

              {/* Tagline */}
              <motion.p
                initial={{ opacity: 0, y: 16 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.7, delay: 0.25 }}
                className="text-lg text-secondary-text font-inter font-light leading-relaxed mb-10 max-w-md md:max-w-md mx-auto md:mx-0"
              >
                Swipe together. Match instantly. Watch happier.
              </motion.p>

              {/* CTAs */}
              <motion.div
                initial={{ opacity: 0, y: 16 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.7, delay: 0.35 }}
                className="flex flex-wrap gap-4 justify-center md:justify-start"
              >
                <a
                  href="/start-session.php"
                  className="inline-flex items-center gap-2 px-7 py-3.5 bg-primary-red text-white text-sm font-bold rounded-md shadow-2xl shadow-red-950/60 hover:bg-red-700 active:scale-95 transition-all duration-200 group"
                >
                  Start Matching
                  <ChevronRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                </a>
                <a
                  href="#how-it-works"
                  className="inline-flex items-center gap-2 px-7 py-3.5 bg-white/5 border border-white/15 text-white text-sm font-semibold rounded-md hover:bg-white/10 backdrop-blur-sm active:scale-95 transition-all duration-200 group"
                >
                  <Play className="w-4 h-4 text-gold-accent fill-gold-accent" />
                  How It Works
                </a>
              </motion.div>
            </div>
          </div>
        </div>

        {/* Scroll indicator */}
        <div className="absolute bottom-8 left-1/2 -translate-x-1/2 z-10 flex flex-col items-center gap-2 opacity-40">
          <div className="w-px h-12 bg-gradient-to-b from-white to-transparent animate-pulse" />
        </div>
      </section>

      {/* ── FILM STRIP — Popular Movies ── */}
      <section className="relative py-0 bg-bg-dark">
        {/* Section label */}
        <div className="max-w-7xl mx-auto px-6 pt-10 pb-5 flex items-center gap-3">
          <Film className="w-5 h-5 text-gold-accent" />
          <span className="text-xs font-bold tracking-[0.3em] uppercase text-gold-accent font-inter">
            Popular Right Now
          </span>
          <div className="flex-1 h-px bg-gradient-to-r from-gold-accent/20 to-transparent" />
        </div>

        {/* Film strip */}
        <div
          ref={filmstripRef}
          className="filmstrip-container"
          onMouseDown={handleDragStart}
          onMouseMove={handleDragMove}
          onMouseUp={handleDragEnd}
          onMouseLeave={handleDragEnd}
        >
          {/* Sprocket holes top */}
          <div className="filmstrip-sprockets top" />

          {/* Poster track */}
          <div className="py-[26px] px-4">
            <div
              ref={trackRef}
              className={`filmstrip-track${isDragging ? " dragging" : ""}`}
            >
              {filmstripList.map((movie, idx) => (
                <div
                  key={idx}
                  className="filmstrip-frame"
                  style={{ width: 180, height: 270 }}
                >
                  {movie.poster ? (
                    <img
                      src={movie.poster}
                      alt={movie.title}
                      className="w-full h-full object-cover pointer-events-none"
                      loading="lazy"
                      draggable={false}
                    />
                  ) : (
                    <div className="w-full h-full bg-gradient-to-b from-zinc-800 to-zinc-900 flex items-center justify-center">
                      <Film className="w-8 h-8 text-secondary-text opacity-30" />
                    </div>
                  )}
                  {/* Overlay info */}
                  <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent opacity-0 hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-3">
                    <div className="flex items-center gap-1 mb-1">
                      <Star className="w-3.5 h-3.5 text-gold-accent fill-gold-accent" />
                      <span className="text-xs text-white font-bold">
                        {movie.rating > 0 ? movie.rating.toFixed(1) : "N/A"}
                      </span>
                    </div>
                    <h4 className="text-sm font-playfair font-bold text-white leading-snug line-clamp-2">
                      {movie.title}
                    </h4>
                    <p className="text-[10px] text-secondary-text mt-0.5 font-inter font-light">
                      {movie.year}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Sprocket holes bottom */}
          <div className="filmstrip-sprockets bottom" />

          {/* Edge fades */}
          <div className="filmstrip-fade-left" />
          <div className="filmstrip-fade-right" />
        </div>
      </section>

      {/* ── HOW IT WORKS ── */}
      <section id="how-it-works" className="py-20 bg-bg-dark border-t border-border-light">
        <div className="max-w-6xl mx-auto px-6">
          <div className="text-center mb-12">
            <p className="text-xs font-bold tracking-widest text-gold-accent uppercase mb-3 font-inter">
              How It Works
            </p>
            <h2 className="text-3xl md:text-4xl font-black font-playfair text-white uppercase tracking-wide">
              4 Step Matchmaking
            </h2>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {steps.map((step, i) => (
              <div key={i} className="relative flex flex-col items-center text-center">
                <motion.div
                  whileHover={{ y: -4 }}
                  className="w-full glass-effect rounded-xl p-6 flex flex-col items-center text-center group cursor-default"
                >
                  <div className="w-14 h-14 rounded-full bg-primary-red/10 border border-primary-red/25 flex items-center justify-center text-primary-red mb-5 group-hover:scale-110 transition-transform duration-300">
                    {step.icon}
                  </div>
                  <h3 className="text-sm font-bold text-white mb-2 font-inter">
                    {step.emoji} {step.title}
                  </h3>
                  <p className="text-xs text-secondary-text leading-relaxed font-inter">
                    {step.desc}
                  </p>
                </motion.div>
                {/* Arrow connector */}
                {i < 3 && (
                  <div className="hidden md:flex absolute top-1/2 -right-4 -translate-y-1/2 z-20 text-border-light/60 text-lg pointer-events-none">
                    ➔
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── POPULAR GENRES ── */}
      <section id="genres" className="py-20 bg-surface-dark border-t border-border-light">
        <div className="max-w-7xl mx-auto px-6">
          <div className="text-center mb-12">
            <p className="text-xs font-bold tracking-widest text-gold-accent uppercase mb-3 font-inter">
              Popular Genres
            </p>
            <h2 className="text-3xl md:text-4xl font-black font-playfair text-white uppercase tracking-wide">
              Choose Your Mood
            </h2>
          </div>

          <div className="grid grid-cols-3 md:grid-cols-6 gap-3 md:gap-4">
            {genres.map((g, i) => (
              <motion.div
                key={i}
                whileHover={{ scale: 1.04, y: -6 }}
                className="relative aspect-[2/3] rounded-lg overflow-hidden border border-border-light cursor-pointer group shadow-lg shadow-black/80"
              >
                <img
                  src={g.img}
                  alt={`${g.name} genre`}
                  className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                  loading="lazy"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent" />
                <div className="absolute inset-0 border border-transparent group-hover:border-gold-accent/40 rounded-lg transition-all duration-300" />
                <div className="absolute bottom-0 left-0 w-full p-3">
                  <h4 className="text-xs font-black tracking-widest font-playfair text-white group-hover:text-gold-accent transition-colors uppercase">
                    {g.name}
                  </h4>
                  <span className="text-[10px] text-secondary-text font-inter font-light tracking-wide">
                    {g.tag}
                  </span>
                </div>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* ── STATS ── */}
      <section className="py-20 bg-bg-dark border-t border-border-light">
        <div className="max-w-4xl mx-auto px-6">
          <div className="grid grid-cols-3 gap-8 divide-x divide-border-light">
            <StatItem
              icon={<Film className="w-8 h-8" />}
              value={stats?.movies ?? 0}
              label="Movies Available"
              suffix="+"
              loading={statsLoading}
            />
            <StatItem
              icon={<Users className="w-8 h-8" />}
              value={stats?.matches ?? 0}
              label="Matches Made"
              suffix="+"
              loading={statsLoading}
            />
            <StatItem
              icon={<Star className="w-8 h-8 fill-gold-accent/20" />}
              value={stats?.satisfaction ?? 0}
              label="Satisfaction Rate"
              suffix="%"
              loading={statsLoading}
            />
          </div>
        </div>
      </section>

      {/* ── FINAL CTA ── */}
      <section className="relative py-28 bg-surface-dark border-t border-border-light overflow-hidden">
        {/* Subtle background image */}
        <div className="absolute inset-0 opacity-10">
          <img src="/images/cinematic_hero_bg.png" alt="" className="w-full h-full object-cover" />
        </div>
        <div className="absolute inset-0 bg-gradient-to-t from-surface-dark via-surface-dark/80 to-surface-dark" />

        <div className="relative z-10 max-w-4xl mx-auto px-6 text-center flex flex-col items-center">
          <h2 className="text-4xl md:text-6xl font-black font-playfair tracking-wide text-white uppercase leading-tight mb-5">
            LIGHTS. CAMERA.{" "}
            <span className="text-primary-red">MATCH.</span>
          </h2>
          <p className="text-lg text-secondary-text font-inter font-light max-w-lg mb-10 leading-relaxed">
            Find your perfect movie with your favorite person. Zero friction, total cinematic alignment.
          </p>
          <a
            href="/start-session.php"
            className="inline-flex items-center gap-2 px-8 py-4 bg-primary-red hover:bg-red-700 text-white font-bold rounded-md shadow-2xl shadow-red-950/60 active:scale-95 transition-all duration-200 group text-base"
          >
            Start Your Session
            <ChevronRight className="w-5 h-5 group-hover:translate-x-1 transition-transform" />
          </a>
        </div>
      </section>

      {/* ── FOOTER ── */}
      <footer id="about" className="bg-bg-dark border-t border-border-light py-16 text-secondary-text">
        <div className="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-10">
          <div className="md:col-span-2">
            <h3 className="text-white text-xl font-bold font-playfair tracking-wider mb-4">
              🎬 MOVIEMATE
            </h3>
            <p className="text-sm leading-relaxed max-w-md font-inter">
              A luxury cinema-themed matchmaking experience designed to make your movie selection instant, fun, and secure. Fully integrated with the TMDb API.
            </p>
          </div>
          <div>
            <h4 className="text-white font-semibold text-xs tracking-widest uppercase mb-4 font-inter">Platform</h4>
            <ul className="space-y-2 text-sm font-inter">
              {["Home", "How It Works", "Genres"].map((l) => (
                <li key={l}><a href={l === "Home" ? "#" : `#${l.toLowerCase().replace(/\s+/g, "-")}`} className="hover:text-white transition-colors">{l}</a></li>
              ))}
              <li><a href="https://github.com/jamiedevera/Moviemate" className="hover:text-white transition-colors">GitHub</a></li>
            </ul>
          </div>
          <div>
            <h4 className="text-white font-semibold text-xs tracking-widest uppercase mb-4 font-inter">Legal / Info</h4>
            <ul className="space-y-2 text-sm font-inter">
              {["TMDb Terms", "Privacy Policy", "Security Audit", "Contact"].map((l) => (
                <li key={l}><a href="#" className="hover:text-white transition-colors">{l}</a></li>
              ))}
            </ul>
          </div>
        </div>
        <div className="max-w-7xl mx-auto px-6 border-t border-border-light mt-10 pt-6 text-center text-xs text-secondary-text/50 flex flex-col md:flex-row items-center justify-between gap-3 font-inter">
          <span>Manila, PH</span>
          <span>© {new Date().getFullYear()} Moviemate. All rights reserved. Powered by TMDb.</span>
        </div>
      </footer>

      {/* ── AUTH MODAL ── */}
      <AnimatePresence>
        {authModalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setAuthModalOpen(false)}
              className="absolute inset-0 bg-black/85 backdrop-blur-sm"
            />
            <motion.div
              initial={{ opacity: 0, scale: 0.95, y: 16 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.95, y: 16 }}
              transition={{ type: "spring", duration: 0.4 }}
              className="relative w-full max-w-md p-8 rounded-xl glass-effect shadow-2xl z-10"
            >
              <button
                onClick={() => setAuthModalOpen(false)}
                className="absolute top-4 right-4 p-1.5 rounded-full text-secondary-text hover:text-white hover:bg-white/5 transition-all"
              >
                <X className="w-4 h-4" />
              </button>

              <h3 className="text-2xl font-bold font-playfair text-white mb-6 uppercase tracking-wide">
                {authMode === "login" ? "Log In" : "Create Account"}
              </h3>

              <form onSubmit={handleAuthSubmit} className="space-y-5">
                {authMode === "signup" && (
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-secondary-text uppercase tracking-wider font-inter">Username</label>
                    <input
                      type="text" name="username" required
                      value={formData.username} onChange={handleInputChange}
                      placeholder="e.g. cinemafan"
                      className="w-full px-4 py-3 bg-surface-dark border border-border-light rounded-md text-white placeholder:text-zinc-600 focus:outline-none focus:border-gold-accent/60 transition-colors font-inter text-sm"
                    />
                  </div>
                )}
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-secondary-text uppercase tracking-wider font-inter">Email</label>
                  <input
                    type="email" name="email" required
                    value={formData.email} onChange={handleInputChange}
                    placeholder="name@example.com"
                    className="w-full px-4 py-3 bg-surface-dark border border-border-light rounded-md text-white placeholder:text-zinc-600 focus:outline-none focus:border-gold-accent/60 transition-colors font-inter text-sm"
                  />
                </div>
                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-secondary-text uppercase tracking-wider font-inter">Password</label>
                  <input
                    type="password" name="password" required
                    value={formData.password} onChange={handleInputChange}
                    placeholder="••••••••"
                    className="w-full px-4 py-3 bg-surface-dark border border-border-light rounded-md text-white placeholder:text-zinc-600 focus:outline-none focus:border-gold-accent/60 transition-colors font-inter text-sm"
                  />
                </div>

                {authError && (
                  <div className="text-sm font-semibold text-primary-red bg-red-950/20 border border-red-900/30 rounded px-3 py-2 font-inter">
                    {authError}
                  </div>
                )}
                {authSuccess && (
                  <div className="text-sm font-semibold text-green-400 bg-green-950/20 border border-green-900/30 rounded px-3 py-2 flex items-center gap-1.5 font-inter">
                    <Check className="w-4 h-4" /> {authSuccess}
                  </div>
                )}

                <button
                  type="submit"
                  disabled={isLoading}
                  className="w-full py-3 bg-primary-red hover:bg-red-700 text-white font-bold rounded-md transition-all shadow-lg active:scale-[0.98] disabled:opacity-50 font-inter text-sm"
                >
                  {isLoading ? "Please wait..." : authMode === "login" ? "Log In" : "Sign Up"}
                </button>
              </form>

              <div className="mt-6 pt-5 border-t border-border-light text-sm text-center font-inter">
                <span className="text-secondary-text">
                  {authMode === "login" ? "Don't have an account?" : "Already have an account?"}
                </span>{" "}
                <button
                  onClick={() => { setAuthMode(authMode === "login" ? "signup" : "login"); setAuthError(""); setAuthSuccess(""); }}
                  className="text-primary-red hover:text-red-400 font-bold transition-colors cursor-pointer"
                >
                  {authMode === "login" ? "Sign Up" : "Log In"}
                </button>
              </div>
            </motion.div>
          </div>
        )}
      </AnimatePresence>

    </div>
  );
}
