import { redirect, notFound } from "next/navigation";
import { query } from "@/lib/db";
import JoinClient from "./JoinClient";

export const dynamic = "force-dynamic";

export default async function JoinPage({
  params,
}: {
  params: Promise<{ session: string }>;
}) {
  const { session } = await params;

  if (!session || !/^[a-f0-9]{16}$/.test(session)) {
    notFound();
  }

  const rows = await query<{
    a_movies: string;
    b_movies: string;
    a_name:   string;
  }>(
    "SELECT a_movies, b_movies, a_name FROM sessions WHERE id = $1",
    [session]
  );

  if (!rows.length) {
    notFound();
  }

  const { a_movies, b_movies, a_name } = rows[0];

  // Already submitted — skip straight to the right place
  if (b_movies) {
    redirect(a_movies ? `/m/${session}/match` : `/m/${session}/b`);
  }

  return <JoinClient session={session} hostName={a_name || "Your MovieMate"} />;
}
