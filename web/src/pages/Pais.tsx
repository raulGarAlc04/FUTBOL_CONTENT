import { type FormEvent, useEffect, useState } from "react";
import { Link, useParams, useSearchParams } from "react-router-dom";
import { Layout } from "../layout/Layout";
import { apiGet } from "../api";

const POS = ["Portero", "Defensa", "Centrocampista", "Delantero", "Entrenador"];

type PaisResponse = {
  pais: Record<string, unknown>;
  competiciones: Record<string, unknown>[];
  equipos: Record<string, unknown>[];
  equiposJugadores: { id: number; nombre: string }[];
  haySinEquipo: boolean;
  jugadoresPais: Record<string, unknown>[];
};

export function Pais() {
  const { id } = useParams();
  const pid = Number(id);
  const [sp, setSp] = useSearchParams();
  const qstr = sp.toString();

  const filtroEquipo = sp.has("equipo_id") ? Number(sp.get("equipo_id")) : -1;
  const filtroPosicion = sp.get("posicion") && POS.includes(String(sp.get("posicion"))) ? String(sp.get("posicion")) : "";
  const ordenJugadores = ["equipo", "dorsal", "posicion"].includes(String(sp.get("orden_jugadores")))
    ? String(sp.get("orden_jugadores"))
    : "posicion";

  const [data, setData] = useState<PaisResponse | null>(null);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    if (!Number.isFinite(pid) || pid <= 0) {
      setErr("País no especificado.");
      return;
    }
    let cancelled = false;
    const suffix = qstr ? `?${qstr}` : "";
    apiGet<PaisResponse>(`/api/pais/${pid}${suffix}`)
      .then((d) => {
        if (!cancelled) setData(d);
      })
      .catch((e: Error) => {
        if (!cancelled) setErr(e.message);
      });
    return () => {
      cancelled = true;
    };
  }, [pid, qstr]);

  const applyFilters = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const n = new URLSearchParams();
    const eq = String(fd.get("equipo_id") ?? "-1");
    const po = String(fd.get("posicion") ?? "");
    const ord = String(fd.get("orden_jugadores") ?? "posicion");
    if (eq !== "-1") n.set("equipo_id", eq);
    if (po) n.set("posicion", po);
    if (ord !== "posicion") n.set("orden_jugadores", ord);
    setSp(n, { replace: true });
  };

  if (err && !data) {
    return (
      <Layout title="País">
        <div className="container" style={{ marginTop: 40 }}>
          <p>{err}</p>
        </div>
      </Layout>
    );
  }

  if (!data) {
    return (
      <Layout title="País">
        <div className="container" style={{ marginTop: 40 }}>
          <p>Cargando…</p>
        </div>
      </Layout>
    );
  }

  const p = data.pais;
  const badge = (pos: string) => {
    let cls = "badge-info";
    if (pos === "Portero") cls = "badge-warning";
    if (pos === "Defensa") cls = "badge-success";
    if (pos === "Delantero") cls = "badge-striker";
    if (pos === "Entrenador") cls = "badge-danger";
    return cls;
  };

  const formKey = `${filtroEquipo}-${filtroPosicion}-${ordenJugadores}`;

  return (
    <Layout title={String(p.nombre ?? "País")}>
      <div className="container" style={{ marginTop: 40 }}>
        <div className="admin-card" style={{ padding: 22, marginBottom: 18 }}>
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 18, flexWrap: "wrap" }}>
            <div style={{ display: "flex", alignItems: "center", gap: 16, minWidth: 0 }}>
              {p.bandera_url ? (
                <img
                  src={String(p.bandera_url)}
                  alt="Bandera"
                  style={{ width: 62, height: "auto", borderRadius: 10, border: "1px solid rgba(255,255,255,0.12)" }}
                />
              ) : (
                <div
                  style={{
                    width: 62,
                    height: 42,
                    borderRadius: 10,
                    border: "1px solid rgba(255,255,255,0.12)",
                    background: "rgba(255,255,255,0.04)",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                  }}
                >
                  🏳️
                </div>
              )}
              <div style={{ minWidth: 0 }}>
                <h1 style={{ margin: 0, fontSize: "2.2rem", letterSpacing: "-0.3px" }}>{String(p.nombre)}</h1>
                <div style={{ marginTop: 6, color: "var(--text-muted)", display: "flex", gap: 10, flexWrap: "wrap" }}>
                  <span>
                    Competiciones: <strong style={{ color: "var(--text-color)" }}>{data.competiciones.length}</strong>
                  </span>
                  <span>
                    Equipos: <strong style={{ color: "var(--text-color)" }}>{data.equipos.length}</strong>
                  </span>
                  <span>
                    Jugadores: <strong style={{ color: "var(--text-color)" }}>{data.jugadoresPais.length}</strong>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <section id="competiciones" style={{ marginBottom: 28 }}>
          <h2 className="section-title">Competiciones</h2>
          {data.competiciones.length === 0 ? (
            <div className="admin-card" style={{ padding: 22, color: "var(--text-muted)" }}>
              No hay competiciones registradas en este país.
            </div>
          ) : (
            <div className="competitions-grid">
              {data.competiciones.map((c) => {
                const row = c as Record<string, unknown>;
                return (
                  <Link key={String(row.id)} to={`/competicion/${row.id}`} className="comp-card">
                    <div className="comp-card-header">
                      {row.logo_url ? <img src={String(row.logo_url)} className="comp-logo" alt="" /> : <div className="comp-logo-placeholder">🏆</div>}
                    </div>
                    <div className="comp-card-body">
                      <h3>{String(row.nombre)}</h3>
                      <div className="comp-meta">
                        <span className="badge badge-info">{String(row.tipo_nombre)}</span>
                        <span>{String(row.temporada_actual)}</span>
                      </div>
                    </div>
                  </Link>
                );
              })}
            </div>
          )}
        </section>

        <section id="equipos" style={{ marginBottom: 28 }}>
          <h2 className="section-title">Equipos</h2>
          {data.equipos.length === 0 ? (
            <div className="admin-card" style={{ padding: 22, color: "var(--text-muted)" }}>
              No hay equipos registrados en este país.
            </div>
          ) : (
            <div className="competitions-grid" style={{ gridTemplateColumns: "repeat(auto-fill, minmax(240px, 1fr))" }}>
              {data.equipos.map((eq) => {
                const e = eq as Record<string, unknown>;
                return (
                  <Link key={String(e.id)} to={`/equipo/${e.id}`} className="comp-card">
                    <div className="comp-card-header" style={{ height: 96 }}>
                      {e.escudo_url ? <img src={String(e.escudo_url)} className="comp-logo" alt="" /> : <div className="comp-logo-placeholder">🛡️</div>}
                    </div>
                    <div className="comp-card-body">
                      <h3>{String(e.nombre)}</h3>
                    </div>
                  </Link>
                );
              })}
            </div>
          )}
        </section>

        <section id="jugadores" style={{ marginBottom: 40 }}>
          <h2 className="section-title">Jugadores</h2>
          {data.jugadoresPais.length === 0 ? (
            <div className="admin-card" style={{ padding: 22, color: "var(--text-muted)" }}>
              No hay jugadores con nacionalidad de este país.
            </div>
          ) : (
            <>
              <div className="admin-card" style={{ padding: "18px 20px" }}>
                <form key={formKey} onSubmit={applyFilters} style={{ display: "flex", gap: 12, flexWrap: "wrap", alignItems: "end" }}>
                  <div style={{ minWidth: 240, flex: 1 }}>
                    <label style={{ display: "block", color: "var(--text-muted)", marginBottom: 6 }}>Equipo</label>
                    <select name="equipo_id" className="form-control-admin" defaultValue={String(filtroEquipo)}>
                      <option value="-1">Todos</option>
                      {data.haySinEquipo ? <option value="0">Sin equipo</option> : null}
                      {data.equiposJugadores.map((eq) => (
                        <option key={eq.id} value={eq.id}>
                          {eq.nombre}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div style={{ minWidth: 220 }}>
                    <label style={{ display: "block", color: "var(--text-muted)", marginBottom: 6 }}>Posición</label>
                    <select name="posicion" className="form-control-admin" defaultValue={filtroPosicion}>
                      <option value="">Todas</option>
                      {POS.map((pname) => (
                        <option key={pname} value={pname}>
                          {pname}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div style={{ minWidth: 220 }}>
                    <label style={{ display: "block", color: "var(--text-muted)", marginBottom: 6 }}>Ordenar por</label>
                    <select name="orden_jugadores" className="form-control-admin" defaultValue={ordenJugadores}>
                      <option value="equipo">Equipo</option>
                      <option value="dorsal">Dorsal</option>
                      <option value="posicion">Posición</option>
                    </select>
                  </div>
                  <div style={{ display: "flex", gap: 10 }}>
                    <button type="submit" className="btn-admin" style={{ margin: 0, padding: "10px 18px" }}>
                      Aplicar
                    </button>
                    <Link to={`/pais/${pid}#jugadores`} className="btn-admin" style={{ margin: 0, padding: "10px 18px" }}>
                      Limpiar
                    </Link>
                  </div>
                </form>
              </div>
              <div className="admin-card" style={{ padding: 0, overflow: "hidden" }}>
                <div className="table-wrap">
                  <table className="admin-table">
                    <thead>
                      <tr>
                        <th style={{ width: 80, textAlign: "center" }}>Dorsal</th>
                        <th>Jugador</th>
                        <th style={{ width: 180 }}>Posición</th>
                        <th>Equipo</th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.jugadoresPais.map((j) => {
                        const row = j as Record<string, unknown>;
                        const tid = Number(row.equipo_id ?? 0);
                        return (
                          <tr key={String(row.id)}>
                            <td style={{ textAlign: "center", fontWeight: 900, color: "var(--accent-color)" }}>
                              {row.dorsal ? Number(row.dorsal) : "-"}
                            </td>
                            <td style={{ fontWeight: 800, color: "#fff" }}>{String(row.nombre)}</td>
                            <td>
                              <span className={`badge ${badge(String(row.posicion ?? ""))}`}>{String(row.posicion ?? "")}</span>
                            </td>
                            <td>
                              {tid > 0 ? (
                                <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                                  {row.equipo_escudo_url ? (
                                    <img src={String(row.equipo_escudo_url)} alt="" style={{ width: 22, height: 22, objectFit: "contain" }} />
                                  ) : (
                                    <div
                                      style={{
                                        width: 22,
                                        height: 22,
                                        borderRadius: 8,
                                        background: "rgba(255,255,255,0.05)",
                                        border: "1px solid rgba(255,255,255,0.08)",
                                        display: "flex",
                                        alignItems: "center",
                                        justifyContent: "center",
                                        fontSize: "0.75rem",
                                      }}
                                    >
                                      🛡️
                                    </div>
                                  )}
                                  <Link to={`/equipo/${tid}`} style={{ textDecoration: "none", color: "#fff", fontWeight: 700 }}>
                                    {String(row.equipo_nombre ?? "")}
                                  </Link>
                                </div>
                              ) : (
                                <span style={{ color: "var(--text-muted)" }}>Sin equipo</span>
                              )}
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              </div>
            </>
          )}
        </section>
      </div>
    </Layout>
  );
}
