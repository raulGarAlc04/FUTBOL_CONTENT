import jwt from "jsonwebtoken";

export const SESSION_COOKIE = "fc_session";

export function getJwtSecret(): string | null {
  const s = process.env.JWT_SECRET?.trim();
  return s && s.length >= 8 ? s : null;
}

export function isCookieLoginConfigured(): boolean {
  const user = process.env.AUTH_USER?.trim();
  const pass = process.env.AUTH_PASSWORD ?? "";
  return Boolean(getJwtSecret() && user && pass.length > 0);
}

export function signSessionToken(): string {
  const secret = getJwtSecret();
  if (!secret) throw new Error("JWT_SECRET no configurado");
  return jwt.sign({ role: "admin" }, secret, { expiresIn: "7d" });
}

export function verifySessionToken(token: string | undefined): { role: string } | null {
  if (!token) return null;
  const secret = getJwtSecret();
  if (!secret) return null;
  try {
    const p = jwt.verify(token, secret);
    if (typeof p === "object" && p !== null && "role" in p && (p as { role: string }).role === "admin") {
      return p as { role: string };
    }
    return null;
  } catch {
    return null;
  }
}

export function credentialsMatch(username: string, password: string): boolean {
  const u = process.env.AUTH_USER?.trim();
  const p = process.env.AUTH_PASSWORD ?? "";
  if (!u || !p) return false;
  return username === u && password === p;
}
