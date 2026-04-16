export type ClasRow = {
  equipo_id: number;
  nombre: string;
  escudo_url: string | null;
  pj: number;
  pg: number;
  pe: number;
  pp: number;
  gf: number;
  gc: number;
  puntos: number;
  pos?: number;
};

export function sortClasificacion(rows: ClasRow[]): ClasRow[] {
  const copy = [...rows];
  copy.sort((a, b) => {
    if (a.puntos !== b.puntos) return b.puntos - a.puntos;
    const dgA = a.gf - a.gc;
    const dgB = b.gf - b.gc;
    if (dgA !== dgB) return dgB - dgA;
    if (a.gf !== b.gf) return b.gf - a.gf;
    return a.nombre.localeCompare(b.nombre, "es", { sensitivity: "base" });
  });
  let pos = 1;
  for (const r of copy) {
    r.pos = pos++;
  }
  return copy;
}
