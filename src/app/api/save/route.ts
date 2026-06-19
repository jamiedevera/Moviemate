import { NextRequest, NextResponse } from "next/server";
import { query } from "@/lib/db";

function err(msg: string, status = 400) {
  return NextResponse.json({ error: msg }, { status });
}

export async function POST(req: NextRequest) {
  try {
    const form      = await req.formData();
    const sessionId = String(form.get("session") ?? "").trim();
    const who       = String(form.get("who") ?? "").trim();
    const movieVals = form.getAll("movies[]");

    if (!sessionId || !/^[a-f0-9]{16}$/.test(sessionId)) {
      return err("Invalid session ID.");
    }

    if (!["A", "B"].includes(who)) {
      return err("Invalid user type.");
    }

    if (!movieVals.length) {
      return err("No movies selected.");
    }

    const movies = [...new Set(
      movieVals
        .map((v) => parseInt(String(v), 10))
        .filter((n) => !isNaN(n) && n > 0)
    )];

    if (movies.length !== 5) {
      return err(`You must select exactly 5 movies. Got ${movies.length}.`);
    }

    // Check session exists and not already submitted
    const rows = await query<{ a_movies: string; b_movies: string }>(
      "SELECT a_movies, b_movies FROM sessions WHERE id = $1",
      [sessionId]
    );

    if (!rows.length) {
      return err("Session not found.");
    }

    const row         = rows[0];
    const alreadyDone = who === "A" ? Boolean(row.a_movies) : Boolean(row.b_movies);

    if (alreadyDone) {
      return err("You have already submitted your choices.");
    }

    // Save
    const col = who === "A" ? "a_movies" : "b_movies";
    await query(
      `UPDATE sessions SET ${col} = $1 WHERE id = $2`,
      [JSON.stringify(movies), sessionId]
    );

    // Check if both done
    const updated = await query<{ a_movies: string; b_movies: string }>(
      "SELECT a_movies, b_movies FROM sessions WHERE id = $1",
      [sessionId]
    );

    const bothDone = Boolean(updated[0]?.a_movies) && Boolean(updated[0]?.b_movies);

    return NextResponse.json({ success: true, bothDone, sessionId });
  } catch (err_) {
    console.error("save error:", err_);
    return NextResponse.json({ error: "Database error." }, { status: 500 });
  }
}
