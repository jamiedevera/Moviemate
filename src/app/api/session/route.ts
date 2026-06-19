import { NextRequest, NextResponse } from "next/server";
import { query } from "@/lib/db";
import { randomBytes } from "crypto";

export async function POST(req: NextRequest) {
  try {
    const form = await req.formData();
    const name = String(form.get("name") ?? "").trim().slice(0, 40);
    const sessionId = randomBytes(8).toString("hex");

    await query(
      "INSERT INTO sessions (id, a_name) VALUES ($1, $2)",
      [sessionId, name || null]
    );

    return NextResponse.json({
      success:   true,
      sessionId,
      url:       `/m/${sessionId}/a`,
    });
  } catch (err) {
    console.error("session create error:", err);
    return NextResponse.json(
      { success: false, error: "Database error." },
      { status: 500 }
    );
  }
}
