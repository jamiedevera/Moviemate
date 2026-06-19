import { NextRequest, NextResponse } from "next/server";
import { query } from "@/lib/db";

export async function GET(
  _req: NextRequest,
  { params }: { params: Promise<{ session: string }> }
) {
  const { session } = await params;

  if (!session || !/^[a-f0-9]{16}$/.test(session)) {
    return NextResponse.json({ error: "invalid_session" }, { status: 400 });
  }

  try {
    const rows = await query<{
      a_movies: string;
      b_movies: string;
      b_joined: boolean;
      b_name:   string;
    }>(
      "SELECT a_movies, b_movies, b_joined, b_name FROM sessions WHERE id = $1",
      [session]
    );

    if (!rows.length) {
      return NextResponse.json({ error: "not_found" }, { status: 404 });
    }

    const row      = rows[0];
    const aDone    = Boolean(row.a_movies);
    const bDone    = Boolean(row.b_movies);
    const bJoined  = Boolean(row.b_joined);

    return NextResponse.json({
      aJoined:  true,
      bJoined,
      bName:    row.b_name ?? "",
      aDone,
      bDone,
      bothDone: aDone && bDone,
      a_movies: aDone,
      b_movies: bDone,
    }, {
      headers: { "Cache-Control": "no-store" },
    });
  } catch (err) {
    console.error("status error:", err);
    return NextResponse.json({ error: "db_error" }, { status: 500 });
  }
}
