import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'MovieMate — Start your session',
  description: 'Set up your MovieMate session and find a movie to watch together.',
};

export default function SessionLayout({ children }: { children: React.ReactNode }) {
  return (
    // Full-screen, no inherited nav/footer from root layout
    <div
      style={{
        minHeight: '100dvh',
        background: '#0a0a0f',
        display: 'flex',
        flexDirection: 'column',
      }}
    >
      {children}
    </div>
  );
}
