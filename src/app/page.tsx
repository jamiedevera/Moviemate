"use client";

import { useEffect, useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { 
  Ticket, 
  Film, 
  Popcorn, 
  Heart, 
  Sparkles, 
  ChevronRight, 
  Play, 
  Star, 
  Users, 
  X, 
  Check,
  Clapperboard,
  Sparkle
} from "lucide-react";

interface Particle {
  id: number;
  size: number;
  left: string;
  delay: string;
  duration: string;
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

  // Monitor scroll for header background
  useEffect(() => {
    const handleScroll = () => {
      if (window.scrollY > 40) {
        setScrolled(true);
      } else {
        setScrolled(false);
      }
    };
    window.addEventListener("scroll", handleScroll);
    return () => window.removeEventListener("scroll", handleScroll);
  }, []);

  // Hydration-safe particle generation
  useEffect(() => {
    const generatedParticles = Array.from({ length: 25 }, (_, i) => ({
      id: i,
      size: Math.random() * 4 + 2,
      left: `${Math.random() * 100}%`,
      delay: `${Math.random() * 15}s`,
      duration: `${Math.random() * 20 + 15}s`,
    }));
    setParticles(generatedParticles);
  }, []);

  const handleAuthSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setAuthError("");
    setAuthSuccess("");
    setIsLoading(true);

    const bodyFormData = new FormData();
    bodyFormData.append("action", authMode);
    if (authMode === "signup") {
      bodyFormData.append("username", formData.username);
    }
    bodyFormData.append("email", formData.email);
    bodyFormData.append("password", formData.password);

    try {
      const res = await fetch("/api/auth", {
        method: "POST",
        body: bodyFormData,
      });
      const data = await res.json();
      if (data.success) {
        setAuthSuccess(authMode === "login" ? "Welcome back! Redirecting..." : "Account created successfully! Redirecting...");
        setTimeout(() => {
          window.location.reload();
        }, 1500);
      } else {
        setAuthError(data.error || "Authentication failed. Please try again.");
      }
    } catch (err) {
      setAuthError("Network error. Please try again.");
    } finally {
      setIsLoading(false);
    }
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  // Genre Poster Card Configuration
  const genres = [
    { name: "ACTION", tag: "Adrenaline Rush", img: "/images/genre_action.png" },
    { name: "SCI-FI", tag: "Beyond Imagination", img: "/images/genre_scifi.png" },
    { name: "HORROR", tag: "Face Your Fears", img: "/images/genre_horror.png" },
    { name: "COMEDY", tag: "Laugh Together", img: "/images/genre_comedy.png" },
    { name: "ROMANCE", tag: "Feel the Love", img: "/images/genre_romance.png" },
    { name: "THRILLER", tag: "Edge of Your Seat", img: "/images/genre_thriller.png" },
  ];

  return (
    <div className="relative min-h-screen bg-bg-dark text-primary-text font-sans overflow-x-hidden">
      
      {/* Film projector background light & particles */}
      <div className="absolute inset-0 bg-radial-gradient(circle at 50% 30%, rgba(220,38,38,0.03) 0%, transparent 70%) pointer-events-none z-0" />
      
      {/* 1. Header/Navbar */}
      <nav className={`fixed top-0 left-0 w-full z-50 scroll-nav ${scrolled ? "scroll-nav-active py-4 shadow-2xl" : "py-6 bg-transparent"}`}>
        <div className="max-w-7xl mx-auto px-6 flex items-center justify-between">
          <a href="#" className="flex items-center gap-2 text-2xl font-bold tracking-tight text-primary-text select-none">
            <span className="text-primary-red">🎬</span>
            <span className="font-playfair tracking-wider">MOVIEMATE</span>
          </a>
          
          <div className="hidden md:flex items-center gap-8 text-sm font-medium text-secondary-text">
            <a href="#" className="hover:text-primary-text transition-colors duration-200">Home</a>
            <a href="#how-it-works" className="hover:text-primary-text transition-colors duration-200">How It Works</a>
            <a href="#genres" className="hover:text-primary-text transition-colors duration-200">Genres</a>
            <a href="#about" className="hover:text-primary-text transition-colors duration-200">About</a>
            <a href="#faq" className="hover:text-primary-text transition-colors duration-200">FAQ</a>
          </div>

          <div className="flex items-center gap-4">
            <button 
              onClick={() => { setAuthMode("login"); setAuthModalOpen(true); }}
              className="px-5 py-2.5 text-sm font-semibold rounded-md border border-transparent hover:border-border-light hover:bg-white/5 transition-all duration-200 cursor-pointer"
            >
              Log In
            </button>
            <a 
              href="/start-session.php"
              className="px-5 py-2.5 text-sm font-semibold rounded-md bg-primary-red text-white hover:bg-red-700 active:scale-95 shadow-lg shadow-red-900/30 transition-all duration-200 flex items-center gap-1.5"
            >
              Start Matching
            </a>
          </div>
        </div>
      </nav>

      {/* 2. Hero Section */}
      <section className="relative min-h-screen flex flex-col justify-center items-center pt-24 pb-12 overflow-hidden z-10">
        {/* Projector Light Beam */}
        <div className="projector-beam" />
        
        {/* Floating Particles in Hero */}
        <div className="dust-container">
          {particles.map((p) => (
            <div
              key={p.id}
              className="dust-particle"
              style={{
                width: `${p.size}px`,
                height: `${p.size}px`,
                left: p.left,
                animationDelay: p.delay,
                animationDuration: p.duration,
              }}
            />
          ))}
        </div>

        {/* Hero Content */}
        <div className="relative max-w-5xl mx-auto px-6 text-center z-10 flex flex-col items-center">
          <motion.div 
            initial={{ opacity: 0, y: -20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-card-dark border border-border-light text-xs font-semibold tracking-wider text-gold-accent uppercase mb-6"
          >
            <Sparkle className="w-3.5 h-3.5 fill-current" />
            🎬 Cinematic Matchmaking
          </motion.div>

          <motion.h1 
            initial={{ opacity: 0, y: 30 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.1 }}
            className="text-4xl md:text-7xl font-extrabold tracking-wider leading-tight text-white mb-6 uppercase"
          >
            Find Your Next Favorite <br />
            <span className="font-playfair text-5xl md:text-8xl tracking-widest cinematic-text-shadow block mt-2">
              MOVIE<span className="text-primary-red font-semibold">MATE</span>
            </span>
          </motion.h1>

          <motion.p 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.2 }}
            className="text-lg md:text-xl text-secondary-text max-w-2xl mb-10 leading-relaxed font-light"
          >
            Swipe together. Match instantly. Watch happier. Discover a premium movie selection experience for dates, friends, and movie nights.
          </motion.p>

          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.3 }}
            className="flex flex-col sm:flex-row gap-4 mb-20"
          >
            <a 
              href="/start-session.php"
              className="px-8 py-4 bg-primary-red text-white text-base font-semibold rounded-md shadow-2xl shadow-red-950/50 hover:bg-red-700 active:scale-95 transition-all duration-200 flex items-center justify-center gap-2 group cursor-pointer"
            >
              Start Matching
              <ChevronRight className="w-5 h-5 group-hover:translate-x-1 transition-transform" />
            </a>
            <a 
              href="#how-it-works"
              className="px-8 py-4 bg-card-dark border border-border-light hover:border-white/20 text-base font-semibold rounded-md hover:bg-white/5 transition-all duration-200 flex items-center justify-center gap-2"
            >
              <Play className="w-4 h-4 text-gold-accent fill-gold-accent" />
              How It Works
            </a>
          </motion.div>

          {/* Luxury Movie Screen Mockup Container */}
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 1, delay: 0.4 }}
            className="relative w-full aspect-[21/9] max-w-4xl rounded-lg overflow-hidden border border-border-light shadow-2xl shadow-red-950/10 mb-8"
          >
            <img 
              src="/images/moviemate_hero_bg.png" 
              alt="Cinematic theater background screen" 
              className="w-full h-full object-cover select-none"
            />
            {/* Screen Projection Mask Overlay */}
            <div className="absolute inset-0 bg-gradient-to-t from-bg-dark/95 via-transparent to-bg-dark/20 pointer-events-none" />
            <div className="absolute inset-0 bg-radial-gradient(ellipse at 50% 10%, rgba(212,175,55,0.05) 0%, transparent 60%) pointer-events-none" />
          </motion.div>
        </div>
      </section>

      {/* 3. How It Works Section */}
      <section id="how-it-works" className="relative py-24 bg-bg-dark border-t border-border-light z-10">
        <div className="max-w-7xl mx-auto px-6">
          <div className="text-center mb-16">
            <h2 className="text-xs font-bold tracking-widest text-gold-accent uppercase mb-2">HOW IT WORKS</h2>
            <p className="text-3xl md:text-4xl font-bold uppercase font-playfair text-white">4 Step Matchmaking</p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
            {/* Card 1 */}
            <div className="glass-effect rounded-xl p-8 relative flex flex-col items-center text-center transition-all duration-300 hover:-translate-y-1 hover:border-gold-accent/30 group">
              <div className="w-14 h-14 rounded-full bg-primary-red/10 border border-primary-red/20 flex items-center justify-center text-primary-red mb-6 group-hover:scale-110 transition-transform duration-300">
                <Ticket className="w-6 h-6" />
              </div>
              <h3 className="text-lg font-bold text-white mb-2">🎟 Create Session</h3>
              <p className="text-sm text-secondary-text leading-relaxed">
                Generate a secure, anonymous movie session link in a single click.
              </p>
              <div className="hidden md:block absolute top-1/2 -right-3 -translate-y-1/2 z-20 text-border-light text-2xl font-light">➔</div>
            </div>

            {/* Card 2 */}
            <div className="glass-effect rounded-xl p-8 relative flex flex-col items-center text-center transition-all duration-300 hover:-translate-y-1 hover:border-gold-accent/30 group">
              <div className="w-14 h-14 rounded-full bg-primary-red/10 border border-primary-red/20 flex items-center justify-center text-primary-red mb-6 group-hover:scale-110 transition-transform duration-300">
                <Popcorn className="w-6 h-6" />
              </div>
              <h3 className="text-lg font-bold text-white mb-2">🍿 Invite Partner</h3>
              <p className="text-sm text-secondary-text leading-relaxed">
                Copy the session link and share it with your partner or friend.
              </p>
              <div className="hidden md:block absolute top-1/2 -right-3 -translate-y-1/2 z-20 text-border-light text-2xl font-light">➔</div>
            </div>

            {/* Card 3 */}
            <div className="glass-effect rounded-xl p-8 relative flex flex-col items-center text-center transition-all duration-300 hover:-translate-y-1 hover:border-gold-accent/30 group">
              <div className="w-14 h-14 rounded-full bg-primary-red/10 border border-primary-red/20 flex items-center justify-center text-primary-red mb-6 group-hover:scale-110 transition-transform duration-300">
                <Clapperboard className="w-6 h-6" />
              </div>
              <h3 className="text-lg font-bold text-white mb-2">🎬 Start Swiping</h3>
              <p className="text-sm text-secondary-text leading-relaxed">
                Browse through popular movies separately and pick your top 5.
              </p>
              <div className="hidden md:block absolute top-1/2 -right-3 -translate-y-1/2 z-20 text-border-light text-2xl font-light">➔</div>
            </div>

            {/* Card 4 */}
            <div className="glass-effect rounded-xl p-8 relative flex flex-col items-center text-center transition-all duration-300 hover:-translate-y-1 hover:border-gold-accent/30 group">
              <div className="w-14 h-14 rounded-full bg-primary-red/10 border border-primary-red/20 flex items-center justify-center text-primary-red mb-6 group-hover:scale-110 transition-transform duration-300">
                <Heart className="w-6 h-6 fill-current" />
              </div>
              <h3 className="text-lg font-bold text-white mb-2">❤️ Match Movies</h3>
              <p className="text-sm text-secondary-text leading-relaxed">
                Get an instant matching overlay showing where your choices intersect!
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* 4. Popular Genres Section */}
      <section id="genres" className="relative py-24 bg-surface-dark border-t border-border-light z-10">
        <div className="max-w-7xl mx-auto px-6">
          <div className="text-center mb-16">
            <h2 className="text-xs font-bold tracking-widest text-gold-accent uppercase mb-2">POPULAR GENRES</h2>
            <p className="text-3xl md:text-4xl font-bold uppercase font-playfair text-white">Choose Your Mood</p>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-6 gap-6">
            {genres.map((g, idx) => (
              <motion.div 
                key={idx}
                whileHover={{ scale: 1.05, y: -8 }}
                className="relative aspect-[2/3] rounded-lg overflow-hidden border border-border-light cursor-pointer group shadow-lg shadow-black/80"
              >
                <img 
                  src={g.img} 
                  alt={`${g.name} Genre poster`} 
                  className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                  loading="lazy"
                />
                {/* Poster dark fade */}
                <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent opacity-80 group-hover:opacity-90 transition-opacity" />
                
                {/* Accent border glow on hover */}
                <div className="absolute inset-0 border border-transparent group-hover:border-gold-accent/40 rounded-lg transition-all duration-300" />
                
                {/* Text Content */}
                <div className="absolute bottom-0 left-0 w-full p-4 flex flex-col justify-end text-left">
                  <h4 className="text-base font-bold tracking-wider font-playfair text-white group-hover:text-gold-accent transition-colors">
                    {g.name}
                  </h4>
                  <span className="text-xs text-secondary-text font-light tracking-wide mt-1">
                    {g.tag}
                  </span>
                </div>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* 5. Statistics Section */}
      <section className="relative py-20 bg-bg-dark border-t border-border-light z-10">
        <div className="max-w-5xl mx-auto px-6">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-12 text-center">
            
            {/* Stat 1 */}
            <div className="flex flex-col items-center">
              <Film className="w-8 h-8 text-gold-accent mb-4" />
              <div className="text-4xl md:text-5xl font-extrabold font-playfair text-white tracking-wide mb-2">5000+</div>
              <div className="text-sm text-secondary-text uppercase tracking-widest font-semibold">Movies Available</div>
            </div>

            {/* Stat 2 */}
            <div className="flex flex-col items-center">
              <Users className="w-8 h-8 text-gold-accent mb-4" />
              <div className="text-4xl md:text-5xl font-extrabold font-playfair text-white tracking-wide mb-2">1000+</div>
              <div className="text-sm text-secondary-text uppercase tracking-widest font-semibold">Matches Made</div>
            </div>

            {/* Stat 3 */}
            <div className="flex flex-col items-center">
              <Star className="w-8 h-8 text-gold-accent mb-4 fill-gold-accent/20" />
              <div className="text-4xl md:text-5xl font-extrabold font-playfair text-white tracking-wide mb-2">98%</div>
              <div className="text-sm text-secondary-text uppercase tracking-widest font-semibold">Satisfaction Rate</div>
            </div>

          </div>
        </div>
      </section>

      {/* 6. Final CTA Section */}
      <section className="relative py-28 bg-surface-dark border-t border-border-light z-10 overflow-hidden">
        {/* Glow */}
        <div className="absolute inset-0 bg-radial-gradient(circle at 50% 50%, rgba(220,38,38,0.02) 0%, transparent 80%) pointer-events-none" />
        
        <div className="max-w-4xl mx-auto px-6 text-center flex flex-col items-center relative z-10">
          <h2 className="text-4xl md:text-6xl font-extrabold font-playfair tracking-wider text-white uppercase mb-4 leading-tight">
            LIGHTS. CAMERA. <span className="text-primary-red">MATCH.</span>
          </h2>
          <p className="text-lg md:text-xl text-secondary-text max-w-xl mb-10 leading-relaxed font-light">
            Find your perfect movie with your favorite person. Zero friction, total cinematic alignment.
          </p>
          <a 
            href="/start-session.php"
            className="px-8 py-4 bg-primary-red hover:bg-red-700 text-white text-base font-bold rounded-md shadow-2xl shadow-red-950/50 active:scale-95 transition-all duration-200 flex items-center justify-center gap-2 group cursor-pointer"
          >
            Start Your Session
            <ChevronRight className="w-5 h-5 group-hover:translate-x-1 transition-transform" />
          </a>
        </div>
      </section>

      {/* 7. Footer */}
      <footer id="about" className="bg-bg-dark border-t border-border-light py-16 text-secondary-text z-10 relative">
        <div className="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-12">
          <div className="md:col-span-2">
            <h3 className="text-white text-xl font-bold tracking-wider font-playfair mb-4">🎬 MOVIEMATE</h3>
            <p className="text-sm leading-relaxed max-w-md">
              A luxury cinema-themed matchmaking experience designed to make your movie selection instant, fun, and secure. Fully integrated with the TMDb API.
            </p>
          </div>
          <div>
            <h4 className="text-white font-semibold text-sm tracking-widest uppercase mb-4">PLATFORM</h4>
            <ul className="space-y-2 text-sm">
              <li><a href="#" className="hover:text-white transition-colors">Home</a></li>
              <li><a href="#how-it-works" className="hover:text-white transition-colors">How It Works</a></li>
              <li><a href="#genres" className="hover:text-white transition-colors">Genres</a></li>
              <li><a href="https://github.com/jamiedevera" className="hover:text-white transition-colors">GitHub Repository</a></li>
            </ul>
          </div>
          <div>
            <h4 className="text-white font-semibold text-sm tracking-widest uppercase mb-4">LEGAL / INFO</h4>
            <ul className="space-y-2 text-sm">
              <li><a href="#" className="hover:text-white transition-colors">TMDb Terms</a></li>
              <li><a href="#" className="hover:text-white transition-colors">Privacy Policy</a></li>
              <li><a href="#" className="hover:text-white transition-colors">Security Audit</a></li>
              <li><a href="#" className="hover:text-white transition-colors">Contact</a></li>
            </ul>
          </div>
        </div>
        <div className="max-w-7xl mx-auto px-6 border-t border-border-light mt-12 pt-6 text-center text-xs text-secondary-text/60 flex flex-col md:flex-row items-center justify-between gap-4">
          <div>Location: Manila, PH</div>
          <div>&copy; {new Date().getFullYear()} Moviemate. All rights reserved. Powered by TMDb.</div>
        </div>
      </footer>

      {/* 8. Auth Modal (Login / Signup) */}
      <AnimatePresence>
        {authModalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            {/* Modal Overlay */}
            <motion.div 
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setAuthModalOpen(false)}
              className="absolute inset-0 bg-black/85 backdrop-blur-sm"
            />
            
            {/* Modal Content */}
            <motion.div 
              initial={{ opacity: 0, scale: 0.95, y: 20 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.95, y: 20 }}
              transition={{ type: "spring", duration: 0.5 }}
              className="relative w-full max-w-md p-8 rounded-xl glass-effect shadow-2xl z-10 text-left"
            >
              <button 
                onClick={() => setAuthModalOpen(false)}
                className="absolute top-4 right-4 p-1.5 rounded-full text-secondary-text hover:text-white hover:bg-white/5 transition-all"
              >
                <X className="w-5 h-5" />
              </button>

              <h3 className="text-2xl font-bold tracking-tight font-playfair text-white mb-6 uppercase">
                {authMode === "login" ? "Log In" : "Create Account"}
              </h3>

              <form onSubmit={handleAuthSubmit} className="space-y-5">
                {authMode === "signup" && (
                  <div className="space-y-1.5">
                    <label className="text-xs font-semibold text-secondary-text uppercase tracking-wider">Username</label>
                    <input 
                      type="text" 
                      name="username" 
                      required 
                      value={formData.username}
                      onChange={handleInputChange}
                      placeholder="e.g. cinemafan"
                      className="w-full px-4 py-3 bg-surface-dark border border-border-light rounded-md text-white placeholder:text-zinc-600 focus:outline-none focus:border-gold-accent transition-colors"
                    />
                  </div>
                )}

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-secondary-text uppercase tracking-wider">Email Address</label>
                  <input 
                    type="email" 
                    name="email" 
                    required 
                    value={formData.email}
                    onChange={handleInputChange}
                    placeholder="name@example.com"
                    className="w-full px-4 py-3 bg-surface-dark border border-border-light rounded-md text-white placeholder:text-zinc-600 focus:outline-none focus:border-gold-accent transition-colors"
                  />
                </div>

                <div className="space-y-1.5">
                  <label className="text-xs font-semibold text-secondary-text uppercase tracking-wider">Password</label>
                  <input 
                    type="password" 
                    name="password" 
                    required 
                    value={formData.password}
                    onChange={handleInputChange}
                    placeholder="••••••••"
                    className="w-full px-4 py-3 bg-surface-dark border border-border-light rounded-md text-white placeholder:text-zinc-600 focus:outline-none focus:border-gold-accent transition-colors"
                  />
                </div>

                {authError && (
                  <div className="text-sm font-semibold text-primary-red bg-red-950/20 border border-red-900/30 rounded px-3 py-2">
                    {authError}
                  </div>
                )}

                {authSuccess && (
                  <div className="text-sm font-semibold text-green-500 bg-green-950/20 border border-green-900/30 rounded px-3 py-2 flex items-center gap-1.5">
                    <Check className="w-4 h-4" />
                    {authSuccess}
                  </div>
                )}

                <button 
                  type="submit" 
                  disabled={isLoading}
                  className="w-full py-3 bg-primary-red hover:bg-red-700 text-white font-bold rounded-md transition-all shadow-lg active:scale-[0.98] disabled:opacity-50"
                >
                  {isLoading ? "Please wait..." : authMode === "login" ? "Log In" : "Sign Up"}
                </button>
              </form>

              <div className="mt-6 pt-6 border-t border-border-light text-sm text-center">
                <span className="text-secondary-text">
                  {authMode === "login" ? "Don't have an account?" : "Already have an account?"}
                </span>{" "}
                <button 
                  onClick={() => {
                    setAuthMode(authMode === "login" ? "signup" : "login");
                    setAuthError("");
                    setAuthSuccess("");
                  }}
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
