import { useEffect, useMemo, useState } from "react";
import { Link, useParams, useSearchParams } from "react-router-dom";
import { Layout } from "../layout/Layout";
import { apiGet } from "../api";

type Jug = Record<string, unknown>;
type Comp = { id: number; nombre: string; temporada_actual: string; logo_url: string | null; tipo_nombre: string };
type Clas = {
  equipo_id: number;
  nombre: string;
  puntos: number;
  pos?: number;
};

export function Equipo() {
  const { id } = useParams();
  const eid = Number(id);
  const [sp, setSp] = useSearchParams();
  const orden = sp.get("orden_jugadores") === "dorsal" ? "dorsal" : "posicion";

  const [equipo, setEquipo] = useState<Record<string, unknown> | null>(null);
  const [jugadores, setJugadores] = useState<Jug[]>([]);
  const [plantilla, setPlantilla] = useState<Record<string, Jug[]>>({});
  const [competiciones, setCompeticiones] = useState<Comp[]>([]);
  const [clasPor, setClasPor] = useState<Record<number, Clas[]>>({});
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    if (!Number.isFinite(eid) || eid <= 0) {
      setErr("Equipo no especificado.");
      return;
    }
    let cancelled = false;
    const q = new URLSearchParams();
    q.set("orden_jugadores", orden);
    apiGet<{
      equipo: Record<string, unknown>;
      jugadores: Jug[];
      plantilla: Record<string, Jug[]>;
      competiciones: Comp[];
      clasificacionesPorCompeticion: Record<string, Clas[]>;
    }>(`/api/equipo/${eid}?${q.toString()}`)
      .then((d) => {
        if (cancelled) return;
        setEquipo(d.equipo);
        setJugadores(d.jugadores as Jug[]);
        setPlantilla(d.plantilla ?? {});
        setCompeticiones(d.competiciones ?? []);
        const raw = d.clasificacionesPorCompeticion ?? {};
        const norm: Record<number, Clas[]> = {};
        for (const [k, v] of Object.entries(raw)) {
          norm[Number(k)] = v as Clas[];
        }
        setClasPor(norm);
      })
      .catch((e: Error) => {
        if (!cancelled) setErr(e.message);
      });
    return () => {
      cancelled = true;
    };
  }, [eid, orden]);

  const setOrden = (next: "posicion" | "dorsal") => {
    const n = new URLSearchParams(sp);
    n.set("orden_jugadores", next);
    setSp(n, { replace: true });
  };

  const posOrder = useMemo(() => ["Portero", "Defensa", "Centrocampista", "Delantero", "Entrenador"], []);

  if (err && !equipo) {
    return (
      <Layout title="Equipo" fullWidth>
        <div className="container" style={{ marginTop: 40 }}>
          <p>{err}</p>
        </div>
      </Layout>
    );
  }

  if (!equipo) {
    return (
      <Layout title="Equipo" fullWidth>
        <div className="container" style={{ marginTop: 40 }}>
          <p>Cargando…</p>
        </div>
      </Layout>
    );
  }

  const badge = (pos: string) => {
    let cls = "badge-info";
    if (pos === "Portero") cls = "badge-warning";
    if (pos === "Defensa") cls = "badge-success";
    if (pos === "Delantero") cls = "badge-striker";
    if (pos === "Entrenador") cls = "badge-danger";
    return cls;
  };

  return (
    <Layout title={String(equipo.nombre ?? "Equipo")} fullWidth>
      <div className="container team-page">
        <div className="team-hero">
          <div className="team-hero-left">
            <div className="team-crest">
              {equipo.escudo_url ? <img src={String(equipo.escudo_url)} alt="" /> : <span className="team-crest-fallback">🛡️</span>}
            </div>
            <div className="team-hero-text">
              <h1 className="team-title">{String(equipo.nombre)}</h1>
              <div className="team-meta">
                {equipo.bandera_url || equipo.pais_nombre ? (
                  <span className="team-chip">
                    {equipo.bandera_url ? <img src={String(equipo.bandera_url)} alt="" /> : null}
                    <span>{String(equipo.pais_nombre ?? "")}</span>
                  </span>
                ) : null}
                {equipo.estadio ? (
                  <span className="team-chip">
                    <span className="team-chip-icon">🏟️</span>
                    <span>{String(equipo.estadio)}</span>
                  </span>
                ) : null}
                {equipo.fundacion ? (
                  <span className="team-chip">
                    <span className="team-chip-icon">📅</span>
                    <span>{String(equipo.fundacion)}</span>
                  </span>
                ) : null}
              </div>
            </div>
          </div>
          <div className="team-hero-actions">
            <button type="button" className="btn-admin team-back" onClick={() => history.back()}>
              Volver
            </button>
          </div>
        </div>

        <div className="team-layout">
          <div className="team-main">
            <section className="team-section">
              <div className="team-section-head">
                <h2 className="team-section-title">Plantilla</h2>
                <div className="team-section-subtitle">{jugadores.length} jugadores</div>
              </div>
              {jugadores.length === 0 ? (
                <div className="team-empty">
                  <div className="team-empty-icon">👤</div>
                  <div>No hay jugadores registrados.</div>
                </div>
              ) : (
                <>
                  <div style={{ display: "flex", justifyContent: "flex-end", marginBottom: 14 }}>
                    <div style={{ display: "flex", gap: 10, alignItems: "center" }}>
                      <select
                        className="form-control-admin"
                        style={{ width: 220 }}
                        value={orden}
                        onChange={(e) => setOrden(e.target.value as "posicion" | "dorsal")}
                      >
                        <option value="posicion">Ordenar por posición</option>
                        <option value="dorsal">Ordenar por dorsal</option>
                      </select>
                    </div>
                  </div>
                  {orden === "posicion" ? (
                    <div className="roster-grid">
                      {posOrder.map((pos) => {
                        const lista = plantilla[pos] ?? [];
                        if (!lista.length) return null;
                        return (
                          <div key={pos} className="roster-card">
                            <div className="roster-card-head">
                              <div className="roster-title">{pos}</div>
                              <div className="roster-count">{lista.length}</div>
                            </div>
                            <div className="roster-list">
                              {lista.map((j) => (
                                <div key={String(j.id)} className="player-row">
                                  <div className="player-number">{j.dorsal ? Number(j.dorsal) : "-"}</div>
                                  <div className="player-info">
                                    <div className="player-name">{String(j.nombre)}</div>
                                  </div>
                                  <div className="player-country">
                                    {j.bandera_url ? <img src={String(j.bandera_url)} alt="" /> : null}
                                    <span>{String(j.pais_nombre ?? "")}</span>
                                  </div>
                                </div>
                              ))}
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  ) : (
                    <div className="admin-card" style={{ padding: 0, overflow: "hidden" }}>
                      <div className="table-wrap">
                        <table className="admin-table">
                          <thead>
                            <tr>
                              <th style={{ width: 90, textAlign: "center" }}>Dorsal</th>
                              <th>Jugador</th>
                              <th style={{ width: 180 }}>Posición</th>
                              <th>Nacionalidad</th>
                            </tr>
                          </thead>
                          <tbody>
                            {jugadores.map((j) => (
                              <tr key={String(j.id)}>
                                <td style={{ textAlign: "center", fontWeight: 900, color: "var(--accent-color)" }}>
                                  {j.dorsal ? Number(j.dorsal) : "-"}
                                </td>
                                <td style={{ fontWeight: 800, color: "#fff" }}>{String(j.nombre)}</td>
                                <td>
                                  <span className={`badge ${badge(String(j.posicion ?? ""))}`}>{String(j.posicion ?? "")}</span>
                                </td>
                                <td>
                                  <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                                    {j.bandera_url ? (
                                      <img src={String(j.bandera_url)} alt="" style={{ width: 22, borderRadius: 4 }} />
                                    ) : null}
                                    <span style={{ color: "var(--text-color)", fontWeight: 700 }}>{String(j.pais_nombre ?? "")}</span>
                                  </div>
                                </td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )}
                </>
              )}
            </section>
          </div>

          <aside className="team-aside">
            <section className="team-section">
              <div className="team-section-head">
                <h2 className="team-section-title">Competiciones</h2>
                <div className="team-section-subtitle">{competiciones.length}</div>
              </div>
              {competiciones.length === 0 ? (
                <div className="team-empty team-empty-compact">
                  <div className="team-empty-icon">🏆</div>
                  <div>Este equipo no está inscrito en ninguna competición.</div>
                </div>
              ) : (
                <div className="team-comp-list">
                  {competiciones.map((c) => (
                    <Link key={c.id} to={`/competicion/${c.id}`} className="team-comp-item">
                      <div className="team-comp-logo">
                        {c.logo_url ? <img src={c.logo_url} alt="" /> : <span>🏆</span>}
                      </div>
                      <div className="team-comp-text">
                        <div className="team-comp-name">{c.nombre}</div>
                        <div className="team-comp-meta">{c.temporada_actual}</div>
                      </div>
                    </Link>
                  ))}
                </div>
              )}
            </section>

            {competiciones.length > 0 ? (
              <section className="team-section team-section-standings">
                <div className="team-section-head">
                  <h2 className="team-section-title">Clasificación</h2>
                  <div className="team-section-subtitle">Equipo marcado</div>
                </div>
                <div className="standings-list">
                  {competiciones.map((c) => {
                    const rows = clasPor[c.id] ?? [];
                    return (
                      <div key={c.id} className="standings-card">
                        <div className="standings-head">
                          <div className="standings-name">{c.nombre}</div>
                          <div className="standings-season">{c.temporada_actual}</div>
                        </div>
                        {rows.length === 0 ? (
                          <div className="team-empty team-empty-compact">
                            <div className="team-empty-icon">📋</div>
                            <div>Sin datos de clasificación.</div>
                          </div>
                        ) : (
                          <div className="standings-table-wrap">
                            <table className="standings-table">
                              <thead>
                                <tr>
                                  <th>#</th>
                                  <th className="standings-team">Equipo</th>
                                  <th>PTS</th>
                                </tr>
                              </thead>
                              <tbody>
                                {rows.map((r) => {
                                  const isMe = Number(r.equipo_id) === eid;
                                  return (
                                    <tr key={r.equipo_id} className={isMe ? "standings-row-me" : ""}>
                                      <td className="standings-pos">{r.pos ?? ""}</td>
                                      <td className="standings-team">
                                        <span className="standings-team-name">{r.nombre}</span>
                                      </td>
                                      <td className="standings-pts">{r.puntos}</td>
                                    </tr>
                                  );
                                })}
                              </tbody>
                            </table>
                          </div>
                        )}
                      </div>
                    );
                  })}
                </div>
              </section>
            ) : null}
          </aside>
        </div>
      </div>
    </Layout>
  );
}
