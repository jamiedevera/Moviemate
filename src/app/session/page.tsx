'use client';

import { useState, useEffect, useRef, useCallback } from 'react';
import styles from './session.module.css';

type Step = 1 | 2;

function buildInviteUrl(sessionId: string): string {
  if (typeof window === 'undefined') return '';
  return `${window.location.origin}/m/${sessionId}`;
}

// ── Step 1 — Name entry + session creation ────────────────────────────────────
function StepName({ onDone }: {
  onDone: (name: string, sessionId: string, chooseUrl: string, inviteUrl: string) => void
}) {
  const [name, setName] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => { inputRef.current?.focus(); }, []);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    const trimmed = name.trim();
    if (!trimmed) return;
    setLoading(true);
    setError('');
    try {
      const res = await fetch('/start-session', {
        method: 'POST',
        headers: { Accept: 'application/json' },
      });
      if (!res.ok) throw new Error(`Server error: ${res.status}`);
      const data = await res.json();
      if (!data.success) throw new Error(data.error ?? 'Failed to start session.');
      const inviteUrl = buildInviteUrl(data.sessionId);
      localStorage.setItem('mm_name', trimmed);
      onDone(trimmed, data.sessionId, data.url, inviteUrl);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Something went wrong.');
      setLoading(false);
    }
  }

  return (
    <div className={styles.step}>
      <span className={styles.stepIcon}>🎟️</span>
      <h1 className={styles.heading}>Let's get your name on the ticket</h1>
      <p className={styles.sub}>We'll save your seat before the movie night begins.</p>
      <form onSubmit={handleSubmit} className={styles.form}>
        <input
          ref={inputRef}
          className={styles.input}
          type="text"
          placeholder="Enter your name"
          value={name}
          onChange={(e) => setName(e.target.value)}
          maxLength={40}
          autoComplete="off"
          disabled={loading}
        />
        {error && <p className={styles.error}>{error}</p>}
        <button className={styles.btn} type="submit" disabled={!name.trim() || loading}>
          {loading ? 'Reserving your seat…' : 'Continue'}
        </button>
      </form>
    </div>
  );
}

// ── Step 2 — Host screening room ──────────────────────────────────────────────
function StepRoom({
  name,
  sessionId,
  inviteUrl,
  chooseUrl,
}: {
  name: string;
  sessionId: string;
  inviteUrl: string;
  chooseUrl: string;
}) {
  const [partnerJoined, setPartnerJoined] = useState(false);
  const [partnerName, setPartnerName] = useState('');
  const [copied, setCopied] = useState(false);
  const [justJoined, setJustJoined] = useState(false);
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const poll = useCallback(async () => {
    try {
      const res = await fetch(`/m/${sessionId}/status`, { cache: 'no-store' });
      if (!res.ok) return;
      const data = await res.json();
      if (data?.bJoined || data?.bothDone) {
        if (!partnerJoined) {
          setPartnerName(data.bName || 'Your MovieMate');
          setJustJoined(true);
          setTimeout(() => setJustJoined(false), 2000);
        }
        setPartnerJoined(true);
        if (intervalRef.current) clearInterval(intervalRef.current);
      }
    } catch { /* silent retry */ }
  }, [sessionId, partnerJoined]);

  useEffect(() => {
    poll();
    intervalRef.current = setInterval(poll, 3000);
    return () => { if (intervalRef.current) clearInterval(intervalRef.current); };
  }, [poll]);

  async function copyInvite() {
    try { await navigator.clipboard.writeText(inviteUrl); }
    catch {
      const ta = document.createElement('textarea');
      ta.value = inviteUrl;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    }
    setCopied(true);
    setTimeout(() => setCopied(false), 2500);
  }

  async function shareInvite() {
    if (navigator.share) {
      try {
        await navigator.share({
          title: 'Join my MovieMate screening',
          text: `${name} has reserved a private screening for you on MovieMate.`,
          url: inviteUrl,
        });
      } catch { /* user cancelled */ }
    } else {
      copyInvite();
    }
  }

  function handleProceed() {
    localStorage.setItem('mm_name', name);
    window.location.href = chooseUrl;
  }

  return (
    <div className={styles.step}>
      {!partnerJoined ? (
        <>
          <span className={styles.stepIcon}>🎟️</span>
          <h1 className={styles.heading}>Your private screening is ready</h1>
          <p className={styles.sub}>Share this invite with your MovieMate and wait for them to enter the theater.</p>

          {/* Seats */}
          <div className={styles.room}>
            <div className={`${styles.seat} ${styles.seatYou}`}>
              <div className={styles.seatAvatar}>🍿</div>
              <div className={styles.seatName}>{name}</div>
              <div className={styles.seatTag}>Already seated</div>
            </div>
            <div className={styles.roomDivider}>···</div>
            <div className={`${styles.seat} ${styles.seatPartnerWaiting}`}>
              <div className={styles.seatAvatar}>💺</div>
              <div className={styles.seatName}>Empty seat</div>
              <div className={styles.seatTag}>Waiting…</div>
            </div>
          </div>

          {/* Invite actions */}
          <div className={styles.inviteSection}>
            <p className={styles.inviteLabel}>📩 Invite your MovieMate</p>
            <div className={styles.inviteBox}>
              <span className={styles.inviteUrl}>{inviteUrl}</span>
            </div>
            <div className={styles.inviteActions}>
              <button className={styles.btnInvite} onClick={copyInvite}>
                {copied ? '✓ Copied!' : 'Copy Invite'}
              </button>
              <button className={styles.btnInviteSecondary} onClick={shareInvite}>
                Share Invite
              </button>
            </div>
          </div>
        </>
      ) : (
        <>
          <span className={styles.stepIcon}>{justJoined ? '🎉' : '🎬'}</span>
          <h1 className={styles.heading}>Both MovieMates have arrived.</h1>
          <p className={styles.sub}>The theater is ready. Time to pick your movies.</p>

          {/* Both seated */}
          <div className={styles.room}>
            <div className={`${styles.seat} ${styles.seatYou}`}>
              <div className={styles.seatAvatar}>🍿</div>
              <div className={styles.seatName}>{name}</div>
              <div className={styles.seatTag}>Seated</div>
            </div>
            <div className={styles.roomDivider}>❤️</div>
            <div className={`${styles.seat} ${styles.seatPartnerJoined}`}>
              <div className={styles.seatAvatar}>🍿</div>
              <div className={styles.seatName}>{partnerName || 'Your MovieMate'}</div>
              <div className={styles.seatTag}>Seated</div>
            </div>
          </div>

          <button className={styles.btn} onClick={handleProceed}>
            Start Choosing Movies
          </button>
        </>
      )}
    </div>
  );
}

// ── Main ──────────────────────────────────────────────────────────────────────
export default function SessionPage() {
  const [step, setStep] = useState<Step>(1);
  const [transitioning, setTransitioning] = useState(false);
  const [session, setSession] = useState({ name: '', sessionId: '', chooseUrl: '', inviteUrl: '' });

  function advance(nextStep: Step) {
    setTransitioning(true);
    setTimeout(() => { setStep(nextStep); setTransitioning(false); }, 280);
  }

  function handleDone(name: string, sessionId: string, chooseUrl: string, inviteUrl: string) {
    setSession({ name, sessionId, chooseUrl, inviteUrl });
    advance(2);
  }

  return (
    <div className={styles.shell}>
      <div className={styles.grain} aria-hidden="true" />
      <div className={styles.dots} aria-label="Step indicator">
        {([1, 2] as Step[]).map((s) => (
          <span key={s} className={`${styles.dot} ${step === s ? styles.dotActive : ''}`} />
        ))}
      </div>
      <div className={`${styles.card} ${transitioning ? styles.cardOut : styles.cardIn}`}>
        {step === 1 && <StepName onDone={handleDone} />}
        {step === 2 && (
          <StepRoom
            name={session.name}
            sessionId={session.sessionId}
            inviteUrl={session.inviteUrl}
            chooseUrl={session.chooseUrl}
          />
        )}
      </div>
    </div>
  );
}
