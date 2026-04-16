import type { CSSProperties } from "react";

export type VisualMark = {
  id: string;
  nombre: string;
  color: string;
  posiciones: string;
  orden: number;
};

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
      const ai = parseInt(part.slice(0, dash), 10);
      const bi = parseInt(part.slice(dash + 1), 10);
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

export function firstMarkForPosition(marks: VisualMark[], position: number): VisualMark | null {
  for (const m of marks) {
    if (posicionEnSpec(position, m.posiciones)) return m;
  }
  return null;
}

export function rowStyleForMark(mark: VisualMark | null): CSSProperties {
  if (!mark) return {};
  const rgb = hexToRgb(mark.color);
  const bg = rgb ? `rgba(${rgb[0]},${rgb[1]},${rgb[2]},0.12)` : "rgba(255,255,255,0.03)";
  return { boxShadow: `inset 6px 0 0 ${mark.color}`, background: bg };
}
