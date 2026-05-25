/* ════════════════════════════════════════
   index.css  —  Homepage cinematic style
   ════════════════════════════════════════ */

.ci-hero {
  position: relative;
  width: 100%;
  height: 72vh;
  min-height: 480px;
  max-height: 720px;
  overflow: hidden;
  border-radius: 0 0 28px 28px;
}

.ci-hero__slides { position:absolute; inset:0; }

.ci-hero__slide {
  position: absolute; inset: 0;
  background-size: cover;
  background-position: center;
  opacity: 0;
  transition: opacity 1.8s ease-in-out;
}

.ci-hero__slide.is-active { opacity: 1; }

.ci-hero__overlay {
  position: absolute; inset: 0;
  background: linear-gradient(180deg, rgba(0,0,0,0.28) 0%, rgba(0,0,0,0.10) 40%, rgba(0,0,0,0.68) 100%);
}

.ci-hero__content {
  position: absolute;
  bottom: 48px; left: 48px;
  z-index: 2;
}

.ci-hero__eyebrow {
  font-family: var(--font-body);
  font-size: 0.72rem;
  font-weight: 500;
  letter-spacing: 0.32em;
  text-transform: uppercase;
  color: rgba(255,255,255,0.7);
  margin-bottom: 10px;
}

.ci-hero__title {
  font-family: var(--font-head);
  font-size: clamp(2.8rem, 7vw, 5.5rem);
  font-weight: 700;
  color: #fff;
  letter-spacing: -0.03em;
  line-height: 1.0;
  margin: 0;
  text-shadow: 0 4px 32px rgba(0,0,0,0.5);
}

.ci-hero__fade {
  position: absolute;
  bottom: 0; left: 0; right: 0;
  height: 120px;
  background: linear-gradient(to top, var(--bg) 0%, transparent 100%);
  pointer-events: none;
  z-index: 3;
}

/* ── MARQUEE ── */
.ci-marquee {
  width: 100%;
  overflow: hidden;
  background: var(--red);
  padding: 12px 0;
}

.ci-marquee__track {
  display: flex;
  width: max-content;
  animation: marqueeScroll 28s linear infinite;
}

@keyframes marqueeScroll {
  from { transform: translateX(0); }
  to   { transform: translateX(-50%); }
}

.ci-marquee__item {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 0 28px;
  font-family: var(--font-body);
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #fff;
  white-space: nowrap;
}

/* ── INTRO ── */
.ci-intro {
  max-width: 860px;
  margin: 0 auto;
  padding: 72px 40px 56px;
  text-align: center;
}

.ci-intro__badge {
  display: inline-block;
  border: 1px solid var(--border-r);
  background: var(--red-dim);
  color: var(--red);
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  padding: 6px 18px;
  border-radius: var(--radius-pill);
  margin-bottom: 32px;
}

.ci-intro__heading {
  font-family: var(--font-head);
  font-size: clamp(2rem, 5vw, 3.4rem);
  font-weight: 400;
  line-height: 1.12;
  letter-spacing: -0.02em;
  color: var(--text);
  margin-bottom: 24px;
}

.ci-intro__heading em     { font-style:italic; color:var(--red); }
.ci-intro__heading strong { font-weight:700; display:block; font-size:1.15em; }

.ci-intro__body {
  font-size: 0.97rem;
  color: var(--text-2);
  line-height: 1.72;
  max-width: 580px;
  margin: 0 auto 44px;
}

.ci-console {
  max-width: 480px;
  margin: 0 auto;
  text-align: left;
}

/* ── BENTO WORKS ── */
.ci-works {
  max-width: 860px;
  margin: 0 auto;
  padding: 16px 40px 80px;
}

.ci-works__heading {
  font-family: var(--font-head);
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--text);
  letter-spacing: -0.02em;
  margin-bottom: 20px;
  text-align: center;
}

.ci-works__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  grid-template-rows: 200px 200px;
  gap: 12px;
}

.ci-work-card--tall { grid-row: 1 / 3; }

.ci-work-card {
  position: relative;
  border-radius: 18px;
  overflow: hidden;
  background-size: cover;
  background-position: center;
  cursor: pointer;
  transition: transform 0.28s var(--spring), box-shadow 0.28s;
}

.ci-work-card:hover {
  transform: scale(1.02);
  box-shadow: 0 16px 40px rgba(0,0,0,0.6);
}

.ci-work-card__overlay {
  position: absolute; inset: 0;
  background: linear-gradient(180deg, transparent 40%, rgba(0,0,0,0.72) 100%);
}

.ci-work-card__label {
  position: absolute;
  bottom: 14px; left: 16px;
  font-family: var(--font-body);
  font-size: 0.82rem;
  font-weight: 600;
  color: #fff;
  letter-spacing: 0.04em;
  z-index: 2;
}

.ci-work-card__cta {
  position: absolute;
  bottom: 12px; right: 12px;
  width: 36px; height: 36px;
  background: #fff;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #000;
  text-decoration: none;
  z-index: 2;
  transition: background 0.18s, transform 0.18s;
}

.ci-work-card__cta:hover { background:var(--red); color:#fff; transform:scale(1.1); }
.ci-work-card__cta--refresh { background:rgba(255,255,255,0.15); color:#fff; }
.ci-work-card__cta--refresh:hover { background:var(--red); }

/* ── RESPONSIVE ── */
@media (max-width:600px) {
  .ci-hero__content { left:24px; bottom:36px; }
  .ci-intro  { padding:52px 20px 40px; }
  .ci-works  { padding:0 20px 60px; }
  .ci-works__grid { grid-template-columns:1fr; grid-template-rows:200px 200px 200px; }
  .ci-work-card--tall { grid-row:auto; }
}
