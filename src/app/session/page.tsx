'use client';

import { useState, useEffect, useRef, useCallback } from 'react';
import styles from './session.module.css';

type Step = 1 | 2 | 3;

interface SessionState {
  name: string;
  sessionId: string;
  chooseUrl: string;
  inviteUrl: string;
}

function buildInviteUrl(sessionId: string): string {
  if (typeof window === 'undefined') return '';
  return `${window.location.origin}/m/${sessionId}`;
}

// ── Step 1 — Name entry ───────────────────────────────────────────────────────
function StepName({ onContinue }: { onContinue: (name: string) => void }) {
  const [name, setName] = useState('');
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => { inputRef.current?.focus(); }, []);

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    const trimmed = name.trim();
    if (trimmed) onContinue(trimmed);
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
        />
        <button className={styles.btn} type="submit" disabled={!name.trim()}>
          Continue
        </button>
      </form>
    </div>
  );
}

// ── Step 2 — Create session ───────────────────────────────────────────────────
function StepStart({
  name,
  onSessionCreated,
}: {
  name: string;
  onSessionCreated: (sessionId: string, chooseUrl: string) => void;
}) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  async function handleStart() {
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
      onSessionCreated(data.sessionId, data.url);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Something went wrong.';
      setError(msg);
      setLoading(false);
    }
  }

  return (
    <div className={styles.step}>
      <span className={styles.stepIcon}>🍿</span>
      <h1 className={styles.heading}>Get started, {name}</h1>
      <p className={styles.sub}>Start your MovieMate session and get ready for a shared movie night.</p>
      {error && <p className={styles.error}>{error}</p>}
      <button className={styles.btn} onClick={handleStart} disabled={loading}>
        {loading ? 'Setting up…' : 'Continue'}
      </button>
    </div>
  );
}

// ── Step 3 — Room / invite ────────────────────────────────────────────────────
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
  const [copied, setCopied] = useState(false);
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const poll = useCallback(async () => {
    try {
      const res = await fetch(`/m/${sessionId}/status`, { cache: 'no-store' });
      if (!res.ok) return;
      const data = await res.json();
      if (data?.bJoined || data?.bothDone) {
        setPartnerJoined(true);
        if (intervalRef.current) clearInterval(intervalRef.current);
      }
    } catch { /* silent retry */ }
  }, [sessionId]);

  useEffect(() => {
    poll();
    intervalRef.current = setInterval(poll, 3000);
    return () => { if (intervalRef.current) clearInterval(intervalRef.current); };
  }, [poll]);

  async function copyInvite() {
    try {
      await navigator.clipboard.writeText(inviteUrl);
    } catch {
      const ta = document.createElement('textarea');
      ta.value = inviteUrl;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    }
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }

  function handleProceed() {
    localStorage.setItem('mm_name', name);
    window.location.href = chooseUrl;
  }

  return (
    <div className={styles.step}>
      <span className={styles.stepIcon}>🎟️</span>
      <h1 className={styles.heading}>MovieMate Room</h1>
      <p className={styles.sub}>
        {partnerJoined ? "You're both in" : 'Waiting for your MovieMate'}
      </p>

      <div className={styles.room}>
        <div className={`${styles.seat} ${styles.seatYou}`}>
          <div className={styles.seatAvatar}>🎬</div>
          <div className={styles.seatLabel}>You</div>
          <div className={styles.seatName}>{name}</div>
        </div>
        <div className={styles.roomDivider}>{partnerJoined ? '❤️' : '···'}</div>
        <div className={`${styles.seat} ${partnerJoined ? styles.seatPartnerJoined : styles.seatPartnerWaiting}`}>
          <div className={styles.seatAvatar}>{partnerJoined ? '🎬' : '?'}</div>
          <div className={styles.seatLabel}>Partner</div>
          <div className={styles.seatName}>{partnerJoined ? 'Joined' : 'Not joined yet'}</div>
        </div>
      </div>

      {/* Invite link display */}
      {!partnerJoined && (
        <div className={styles.inviteBox}>
          <span className={styles.inviteUrl}>{inviteUrl}</span>
          <button className={styles.copyBtn} onClick={copyInvite}>
            {copied ? '✓ Copied' : 'Copy link'}
          </button>
        </div>
      )}

      {!partnerJoined && (
        <button className={styles.btnSecondary} onClick={copyInvite}>
          {copied ? '✓ Copied!' : 'Invite MovieMate'}
        </button>
      )}

      {partnerJoined && (
        <button className={styles.btn} onClick={handleProceed}>
          Pick your movies
        </button>
      )}
    </div>
  );
}

// ── Main page — always starts at step 1 ──────────────────────────────────────
export default function SessionPage() {
  const [step, setStep] = useState<Step>(1); // always start at 1
  const [transitioning, setTransitioning] = useState(false);
  const [session, setSession] = useState<SessionState>({
    name: '',
    sessionId: '',
    chooseUrl: '',
    inviteUrl: '',
  });

  function advance(nextStep: Step) {
    setTransitioning(true);
    setTimeout(() => {
      setStep(nextStep);
      setTransitioning(false);
    }, 280);
  }

  function handleNameContinue(name: string) {
    // Store name but don't skip step — user explicitly entered it this session
    setSession((s) => ({ ...s, name }));
    advance(2);
  }

  function handleSessionCreated(sessionId: string, chooseUrl: string) {
    const inviteUrl = buildInviteUrl(sessionId);
    // Persist name now that session is confirmed
    localStorage.setItem('mm_name', session.name);
    setSession((s) => ({ ...s, sessionId, chooseUrl, inviteUrl }));
    advance(3);
  }

  return (
    <div className={styles.shell}>
      <div className={styles.grain} aria-hidden="true" />

      <div className={styles.dots} aria-label="Step indicator">
        {([1, 2, 3] as Step[]).map((s) => (
          <span key={s} className={`${styles.dot} ${step === s ? styles.dotActive : ''}`} />
        ))}
      </div>

      <div className={`${styles.card} ${transitioning ? styles.cardOut : styles.cardIn}`}>
        {step === 1 && <StepName onContinue={handleNameContinue} />}
        {step === 2 && (
          <StepStart name={session.name} onSessionCreated={handleSessionCreated} />
        )}
        {step === 3 && (
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
