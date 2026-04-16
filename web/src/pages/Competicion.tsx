import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { Layout } from "../layout/Layout";
import { apiGet } from "../api";
import { firstMarkForPosition, rowStyleForMark, type VisualMark } from "../lib/visualMarks";

type Clas = {
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

type Comp = {
  nombre: string;
  temporada_actual: string;
  logo_url: string | null;
  tipo_nombre: string;
};

export function Competicion() {
  const { id } = useParams();
  const cid = Number(id);
  const [comp, setComp] = useState<Comp | null>(null);
  const [clas, setClas] = useState<Clas[]>([]);
  const [equipos, setEquipos] = useState<Record<string, unknown>[]>([]);
  const [marcas, setMarcas] = useState<VisualMark[]>([]);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    if (!Number.isFinite(cid) || cid <= 0) {
      setErr("Competición no especificada.");
      return;
    }
    let cancelled = false;
    apiGet<{ comp: Comp; clasificacion: Clas[]; equipos: Record<string, unknown>[]; marcasVisuales: VisualMark[] }>(
      `/api/competiciones/${cid}`
    )
      .then((d) => {
        if (cancelled) return;
        setComp(d.comp);
        setClas(d.clasificacion);
        setEquipos(d.equipos);
        setMarcas(d.marcasVisuales ?? []);
      })
      .catch((e: Error) => {
        if (!cancelled) setErr(e.message);
      });
    return () => {
      cancelled = true;
    };
  }, [cid]);

  if (err && !comp) {
    return (
      <Layout title="Competición">
        <div className="container page-shell">
          <div className="message-panel">{err}</div>
        </div>
      </Layout>
    );
  }

  if (!comp) {
    return (
      <Layout title="Competición">
        <div className="container page-shell">
          <div className="message-panel">Cargando…</div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout title={comp.nombre}>
      <div className="container page-shell">
        <header className="page-hero">
          <div className="page-hero-main">
            <div className="page-hero-logo">
              {comp.logo_url ? <img src={comp.logo_url} alt="" /> : <span className="page-hero-icon">🏆</span>}
            </div>
            <div>
              <h1 className="page-hero-title">{comp.nombre}</h1>
              <p className="page-hero-meta">
                {comp.tipo_nombre} · {comp.temporada_actual}
              </p>
            </div>
          </div>
          <button type="button" className="btn-back" onClick={() => history.back()}>
            ← Volver
          </button>
        </header>

        <section style={{ marginBottom: 40 }}>
          <h2 className="section-title">Clasificación</h2>
          {clas.length === 0 ? (
            <div className="message-panel">No hay equipos inscritos.</div>
          ) : (
            <>
              <div className="admin-card" style={{ padding: 0, overflow: "hidden" }}>
                <div className="table-wrap">
                  <table className="admin-table">
                    <thead>
                      <tr>
                        <th style={{ width: 60, textAlign: "center" }}>#</th>
                        <th>Equipo</th>
                        <th style={{ textAlign: "center" }}>PJ</th>
                        <th style={{ textAlign: "center" }}>PG</th>
                        <th style={{ textAlign: "center" }}>PE</th>
                        <th style={{ textAlign: "center" }}>PP</th>
                        <th style={{ textAlign: "center" }}>GF</th>
                        <th style={{ textAlign: "center" }}>GC</th>
                        <th style={{ textAlign: "center" }}>DG</th>
                        <th style={{ textAlign: "center" }}>PTS</th>
                      </tr>
                    </thead>
                    <tbody>
                      {clas.map((row, idx) => {
                        const pos = idx + 1;
                        const mark = firstMarkForPosition(marcas, pos);
                        const dg = row.gf - row.gc;
                        return (
                          <tr key={row.equipo_id} style={rowStyleForMark(mark)}>
                            <td style={{ textAlign: "center", fontWeight: "bold", color: "var(--text-muted)" }}>{pos}</td>
                            <td>
                              <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
                                {row.escudo_url ? (
                                  <img src={row.escudo_url} alt="" style={{ width: 28, height: 28, objectFit: "contain" }} />
                                ) : (
                                  <div
                                    style={{
                                      width: 28,
                                      height: 28,
                                      background: "rgba(255,255,255,0.05)",
                                      borderRadius: "50%",
                                      display: "flex",
                                      alignItems: "center",
                                      justifyContent: "center",
                                      fontSize: "0.7rem",
                                    }}
                                    aria-hidden="true"
                                  >
                                    🛡️
                                  </div>
                                )}
                                <Link to={`/equipo/${row.equipo_id}`} style={{ textDecoration: "none", color: "#fff", fontWeight: 700 }}>
                                  {row.nombre}
                                </Link>
                              </div>
                            </td>
                            <td style={{ textAlign: "center" }}>{row.pj}</td>
                            <td style={{ textAlign: "center" }}>{row.pg}</td>
                            <td style={{ textAlign: "center" }}>{row.pe}</td>
                            <td style={{ textAlign: "center" }}>{row.pp}</td>
                            <td style={{ textAlign: "center" }}>{row.gf}</td>
                            <td style={{ textAlign: "center" }}>{row.gc}</td>
                            <td style={{ textAlign: "center" }}>{dg > 0 ? `+${dg}` : dg}</td>
                            <td style={{ textAlign: "center", fontWeight: 900, color: "var(--accent-color)" }}>{row.puntos}</td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              </div>
              {marcas.length > 0 ? (
                <div className="admin-card" style={{ padding: "18px 20px", marginTop: 14 }}>
                  <div style={{ fontWeight: 800, marginBottom: 12 }}>Leyenda</div>
                  <div className="legend-strip">
                    {marcas.map((m) => (
                      <div key={m.id} className="legend-item">
                        <span className="legend-swatch" style={{ background: m.color }} />
                        <span className="legend-name">{m.nombre}</span>
                        <span className="legend-pos">{m.posiciones}</span>
                      </div>
                    ))}
                  </div>
                </div>
              ) : null}
            </>
          )}
        </section>

        <section style={{ marginBottom: 60 }}>
          <h2 className="section-title">Equipos</h2>
          {equipos.length === 0 ? (
            <div className="message-panel">No hay equipos inscritos.</div>
          ) : (
            <div className="competitions-grid" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(260px, 1fr))" }}>
              {equipos.map((eq) => {
                const e = eq as Record<string, string | number | null>;
                return (
                  <Link key={String(e.id)} to={`/equipo/${e.id}`} className="comp-card">
                    <div className="comp-card-header" style={{ justifyContent: "flex-start", gap: 12 }}>
                      {e.escudo_url ? (
                        <img src={String(e.escudo_url)} className="comp-logo" style={{ width: 44, height: 44, objectFit: "contain" }} alt="" />
                      ) : (
                        <div className="comp-logo-placeholder" style={{ width: 44, height: 44 }}>
                          🛡️
                        </div>
                      )}
                      <div>
                        <h3 style={{ margin: 0, color: "#fff" }}>{String(e.nombre)}</h3>
                        <div className="comp-meta">
                          {e.bandera_url ? <img src={String(e.bandera_url)} alt="" className="comp-flag" /> : null}
                          <span>{String(e.pais_nombre ?? "")}</span>
                        </div>
                      </div>
                    </div>
                  </Link>
                );
              })}
            </div>
          )}
        </section>
      </div>
    </Layout>
  );
}
