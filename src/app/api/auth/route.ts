import { NextRequest, NextResponse } from "next/server";
import { query } from "@/lib/db";
import { createHash } from "crypto";

// Simple argon2id-compatible hashing using Node's built-in crypto
// For production you'd use bcrypt or argon2 npm package
// Here we use a strong PBKDF2 as a serverless-safe alternative
import { pbkdf2Sync, randomBytes } from "crypto";

function hashPassword(password: string, salt?: string): { hash: string; salt: string } {
  const s = salt ?? randomBytes(16).toString("hex");
  const hash = pbkdf2Sync(password, s, 310000, 64, "sha512").toString("hex");
  return { hash: `pbkdf2:${s}:${hash}`, salt: s };
}

function verifyPassword(password: string, stored: string): boolean {
  if (stored.startsWith("pbkdf2:")) {
    const [, salt, hash] = stored.split(":");
    const { hash: computed } = hashPassword(password, salt);
    return computed === stored;
  }
  // Legacy argon2id check — can't verify without native module, reject gracefully
  return false;
}

function jsonErr(msg: string, status = 400) {
  return NextResponse.json({ success: false, error: msg }, { status });
}

export async function POST(req: NextRequest) {
  try {
    const form     = await req.formData();
    const action   = String(form.get("action") ?? "");
    const email    = String(form.get("email") ?? "").trim().toLowerCase();
    const password = String(form.get("password") ?? "");

    if (!email || !password) {
      return jsonErr("Email and password are required.");
    }

    if (action === "signup") {
      const username = String(form.get("username") ?? "").trim();
      if (!username) return jsonErr("Username is required.");

      const existing = await query(
        "SELECT id FROM users WHERE email = $1 OR username = $2",
        [email, username]
      );
      if (existing.length) {
        return jsonErr("Email or username already taken.");
      }

      const { hash } = hashPassword(password);
      await query(
        "INSERT INTO users (username, email, password_hash) VALUES ($1, $2, $3)",
        [username, email, hash]
      );

      return NextResponse.json({ success: true });
    }

    if (action === "login") {
      const rows = await query<{ id: number; username: string; password_hash: string }>(
        "SELECT id, username, password_hash FROM users WHERE email = $1",
        [email]
      );

      if (!rows.length || !verifyPassword(password, rows[0].password_hash)) {
        return jsonErr("Invalid email or password.");
      }

      return NextResponse.json({ success: true, username: rows[0].username });
    }

    return jsonErr("Invalid action.");
  } catch (err) {
    console.error("auth error:", err);
    return jsonErr("An error occurred.", 500);
  }
}
