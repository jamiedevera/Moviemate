import { NextRequest, NextResponse } from "next/server";
import { query } from "@/lib/db";

export async function POST(
  req: NextRequest,
  { params }: { params: Promise<{ session: string }> }
) {
  const { session } = await params;

  if (!session || !/^[a-f0-9]{16}$/.test(session)) {
    return NextResponse.json({ error: "invalid_session" }, { status: 400 });
  }

  try {
    const body = await req.json().catch(() => ({}));
    const name = String(body.name ?? "").trim().slice(0, 40);

    await query(
      "UPDATE sessions SET b_joined = TRUE, b_name = $1 WHERE id = $2",
      [name || null, session]
    );

    return NextResponse.json({ success: true });
  } catch (err) {
    console.error("join-b error:", err);
    return NextResponse.json({ error: "db_error" }, { status: 500 });
  }
}
