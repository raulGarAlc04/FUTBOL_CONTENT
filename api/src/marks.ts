import fs from "node:fs";
import path from "node:path";
import crypto from "node:crypto";

export type VisualMark = {
  id: string;
  nombre: string;
  color: string;
  posiciones: string;
  orden: number;
};

export function marksFilePath(): string {
  const p = process.env.MARKS_JSON_PATH?.trim();
  if (p) return path.isAbsolute(p) ? p : path.resolve(process.cwd(), p);
  return path.resolve(process.cwd(), "data", "clasificacion_visual.json");
}

export function normalizeColor(color: string): string {
  const c = color.trim();
  if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(c)) return c.toLowerCase();
  return "#ffffff";
}

export function normalizePosiciones(spec: string): string {
  let s = spec.replace(/[;|]/g, ",");
  s = s.replace(/[^0-9,\-\s]/g, "");
  s = s.replace(/\s+/g, "");
  s = s.replace(/,+/g, ",");
  return s.replace(/^,+|,+$/g, "");
}

export function posicionEnSpec(pos: number, spec: string): boolean {
  const p = Math.floor(pos);
  if (p <= 0) return false;
  const s = normalizePosiciones(spec);
  if (!s) return false;
  for (const part of s.split(",")) {
    if (!part) continue;
    const dash = part.indexOf("-");
    if (dash !== -1) {
      const a = part.slice(0, dash);
      const b = part.slice(dash + 1);
      if (!a || !b) continue;
      const ai = parseInt(a, 10);
      const bi = parseInt(b, 10);
      if (ai <= 0 || bi <= 0) continue;
      const min = Math.min(ai, bi);
      const max = Math.max(ai, bi);
      if (p >= min && p <= max) return true;
      continue;
    }
    if (parseInt(part, 10) === p) return true;
  }
  return false;
}

export function hexToRgb(hex: string): [number, number, number] | null {
  let h = hex.replace("#", "");
  if (h.length === 3) {
    h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
  }
  if (h.length !== 6) return null;
  return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
}

function loadAll(): Record<string, unknown> {
  const file = marksFilePath();
  if (!fs.existsSync(file)) return {};
  const raw = fs.readFileSync(file, "utf8");
  try {
    const data = JSON.parse(raw) as unknown;
    return typeof data === "object" && data !== null && !Array.isArray(data) ? (data as Record<string, unknown>) : {};
  } catch {
    return {};
  }
}

function saveAll(data: Record<string, unknown>): boolean {
  const file = marksFilePath();
  const dir = path.dirname(file);
  fs.mkdirSync(dir, { recursive: true });
  const json = JSON.stringify(data, null, 2);
  const tmp = `${file}.${process.pid}.tmp`;
  fs.writeFileSync(tmp, json, "utf8");
  fs.renameSync(tmp, file);
  return true;
}

export function getMarks(competicionId: number): VisualMark[] {
  const cid = Math.floor(competicionId);
  if (cid <= 0) return [];
  const all = loadAll();
  const key = String(cid);
  const items = all[key];
  if (!Array.isArray(items)) return [];
  const out: VisualMark[] = [];
  for (const m of items) {
    if (!m || typeof m !== "object") continue;
    const row = m as Record<string, unknown>;
    const id = String(row.id ?? "");
    if (!id) continue;
    const nombre = String(row.nombre ?? "").trim();
    const posiciones = normalizePosiciones(String(row.posiciones ?? ""));
    if (!nombre || !posiciones) continue;
    out.push({
      id,
      nombre,
      color: normalizeColor(String(row.color ?? "")),
      posiciones,
      orden: Number(row.orden ?? 0) || 0,
    });
  }
  out.sort((a, b) => (a.orden !== b.orden ? a.orden - b.orden : a.id.localeCompare(b.id)));
  return out;
}

export function setMarks(competicionId: number, marks: VisualMark[]): boolean {
  const cid = Math.floor(competicionId);
  if (cid <= 0) return false;
  const clean: VisualMark[] = [];
  for (const m of marks) {
    const id = String(m.id ?? "");
    if (!id) continue;
    const nombre = String(m.nombre ?? "").trim();
    const posiciones = normalizePosiciones(String(m.posiciones ?? ""));
    if (!nombre || !posiciones) continue;
    clean.push({
      id,
      nombre,
      color: normalizeColor(String(m.color ?? "")),
      posiciones,
      orden: Number(m.orden ?? 0) || 0,
    });
  }
  const all = loadAll();
  all[String(cid)] = clean;
  return saveAll(all);
}

export function newMarkId(): string {
  try {
    return crypto.randomBytes(8).toString("hex");
  } catch {
    return crypto.randomUUID().replace(/-/g, "");
  }
}
