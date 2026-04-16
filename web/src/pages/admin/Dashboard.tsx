import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Layout } from "../../layout/Layout";
import { apiGet, apiPost } from "../../api";
import { AdminKeyBanner } from "./AdminKeyBanner";

type Stats = {
  continentes: number;
  paises: number;
  ligas: number;
  equipos: number;
  jugadores: number;
  equipos_sin_liga: number;
  equipos_sin_plantilla: number;
};

type LeagueRow = {
  id: number;
  nombre: string;
  temporada_actual: string;
  logo_url: string | null;
  equipos_inscritos: number;
};

export function AdminDashboard() {
  const [stats, setStats] = useState<Stats | null>(null);
  const [active, setActive] = useState<LeagueRow[]>([]);
  const [recent, setRecent] = useState<LeagueRow[]>([]);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    Promise.all([
      apiGet<Stats>("/api/admin/stats"),
      apiGet<LeagueRow[]>("/api/admin/active-leagues"),
      apiGet<LeagueRow[]>("/api/admin/recent-leagues"),
    ])
      .then(([s, a, r]) => {
        if (!cancelled) {
          setStats(s);
          setActive(a);
          setRecent(r);
        }
      })
      .catch((e: Error) => {
        if (!cancelled) setErr(e.message);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const run = async (path: string, msg: string) => {
    if (!confirm(msg)) return;
    try {
      const res = await apiPost<{ ok?: boolean; deleted?: number }>(path);
      alert(JSON.stringify(res));
      window.location.reload();
    } catch (e) {
      alert((e as Error).message);
    }
  };

  return (
    <Layout title="Administración">
      <div className="container" style={{ marginTop: 40, marginBottom: 60 }}>
        <AdminKeyBanner />
        {err ? <div className="message-panel">{err}</div> : null}

        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 30 }}>
          <div>
            <h1 style={{ fontSize: "2.5rem", marginBottom: 5 }}>Panel de Control</h1>
            <p style={{ color: "var(--text-muted)" }}>Bienvenido al sistema de gestión de Futbol Data.</p>
          </div>
          <div
            style={{
              background: "rgba(212, 175, 55, 0.1)",
              padding: "10px 20px",
              borderRadius: 12,
              border: "1px solid var(--accent-color)",
            }}
          >
            <span style={{ color: "var(--accent-color)", fontWeight: "bold" }}>Versión 2026.2</span>
          </div>
        </div>

        {stats ? (
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))", gap: 20, marginBottom: 40 }}>
            <div className="admin-card" style={{ padding: 20, marginBottom: 0, textAlign: "center", borderLeft: "4px solid #3b82f6" }}>
              <div style={{ fontSize: "0.8rem", textTransform: "uppercase", color: "var(--text-muted)", marginBottom: 5 }}>Ligas</div>
              <div style={{ fontSize: "2rem", fontWeight: "bold", color: "#fff" }}>{stats.ligas}</div>
            </div>
            <div className="admin-card" style={{ padding: 20, marginBottom: 0, textAlign: "center", borderLeft: "4px solid #10b981" }}>
              <div style={{ fontSize: "0.8rem", textTransform: "uppercase", color: "var(--text-muted)", marginBottom: 5 }}>Equipos</div>
              <div style={{ fontSize: "2rem", fontWeight: "bold", color: "#fff" }}>{stats.equipos}</div>
            </div>
            <div className="admin-card" style={{ padding: 20, marginBottom: 0, textAlign: "center", borderLeft: "4px solid #f59e0b" }}>
              <div style={{ fontSize: "0.8rem", textTransform: "uppercase", color: "var(--text-muted)", marginBottom: 5 }}>Jugadores</div>
              <div style={{ fontSize: "2rem", fontWeight: "bold", color: "#fff" }}>{stats.jugadores}</div>
            </div>
            <div className="admin-card" style={{ padding: 20, marginBottom: 0, textAlign: "center", borderLeft: "4px solid #8b5cf6" }}>
              <div style={{ fontSize: "0.8rem", textTransform: "uppercase", color: "var(--text-muted)", marginBottom: 5 }}>Equipos sin Liga</div>
              <div style={{ fontSize: "2rem", fontWeight: "bold", color: "#fff" }}>{stats.equipos_sin_liga}</div>
            </div>
            <div className="admin-card" style={{ padding: 20, marginBottom: 0, textAlign: "center", borderLeft: "4px solid #ef4444" }}>
              <div style={{ fontSize: "0.8rem", textTransform: "uppercase", color: "var(--text-muted)", marginBottom: 5 }}>Equipos sin Plantilla</div>
              <div style={{ fontSize: "2rem", fontWeight: "bold", color: "#fff" }}>{stats.equipos_sin_plantilla}</div>
            </div>
          </div>
        ) : null}

        <div style={{ display: "grid", gridTemplateColumns: "2fr 1fr", gap: 30 }}>
          <div>
            <h2 style={{ fontSize: "1.5rem", marginBottom: 20 }}>Gestión de Entidades</h2>
            <div className="admin-grid" style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(200px, 1fr))", gap: 15 }}>
              <Link to="/admin/continentes" className="comp-card" style={{ padding: 20, textDecoration: "none" }}>
                <h3 style={{ fontSize: "1.1rem", color: "#fff" }}>Continentes</h3>
                <p style={{ fontSize: "0.85rem", color: "var(--text-muted)" }}>Administrar regiones</p>
              </Link>
              <Link to="/admin/paises" className="comp-card" style={{ padding: 20, textDecoration: "none" }}>
                <h3 style={{ fontSize: "1.1rem", color: "#fff" }}>Países</h3>
                <p style={{ fontSize: "0.85rem", color: "var(--text-muted)" }}>Administrar naciones</p>
              </Link>
              <Link to="/admin/competiciones" className="comp-card" style={{ padding: 20, textDecoration: "none" }}>
                <h3 style={{ fontSize: "1.1rem", color: "#fff" }}>Competiciones</h3>
                <p style={{ fontSize: "0.85rem", color: "var(--text-muted)" }}>Ligas</p>
              </Link>
              <Link to="/admin/equipos" className="comp-card" style={{ padding: 20, textDecoration: "none" }}>
                <h3 style={{ fontSize: "1.1rem", color: "#fff" }}>Equipos</h3>
                <p style={{ fontSize: "0.85rem", color: "var(--text-muted)" }}>Clubes de fútbol</p>
              </Link>
            </div>

            <div style={{ marginTop: 30 }}>
              <h2 style={{ fontSize: "1.5rem", marginBottom: 20 }}>Ligas activas</h2>
              <div className="admin-card" style={{ padding: 0, overflow: "hidden" }}>
                <div className="table-wrap">
                  <table className="admin-table">
                    <thead>
                      <tr>
                        <th>Liga</th>
                        <th style={{ textAlign: "center" }}>Equipos</th>
                        <th style={{ textAlign: "right", paddingRight: 25 }}>Acciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      {active.map((l) => (
                        <tr key={l.id}>
                          <td>
                            <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                              {l.logo_url ? (
                                <img src={l.logo_url} style={{ width: 26, height: 26, objectFit: "contain" }} alt="" />
                              ) : (
                                <div
                                  style={{
                                    width: 26,
                                    height: 26,
                                    background: "rgba(255,255,255,0.06)",
                                    borderRadius: "50%",
                                    display: "flex",
                                    alignItems: "center",
                                    justifyContent: "center",
                                    fontSize: "0.7rem",
                                  }}
                                >
                                  🏆
                                </div>
                              )}
                              <div>
                                <div style={{ fontWeight: 700, color: "#fff" }}>{l.nombre}</div>
                                <div style={{ fontSize: "0.8rem", color: "var(--text-muted)" }}>{l.temporada_actual}</div>
                              </div>
                            </div>
                          </td>
                          <td style={{ textAlign: "center", fontWeight: 800, color: "var(--text-muted)" }}>{l.equipos_inscritos}</td>
                          <td style={{ textAlign: "right", paddingRight: 25 }}>
                            <div style={{ display: "flex", justifyContent: "flex-end", gap: 10, flexWrap: "wrap" }}>
                              <Link to={`/competicion/${l.id}`} className="btn-admin" style={{ margin: 0, padding: "6px 12px", background: "var(--accent-color)" }}>
                                Ver web
                              </Link>
                              <Link
                                to={`/admin/competicion/${l.id}/marcas`}
                                className="btn-admin"
                                style={{ margin: 0, padding: "6px 12px", background: "rgba(255,255,255,0.05)", color: "var(--text-muted)" }}
                              >
                                Marcas
                              </Link>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <div>
            <h2 style={{ fontSize: "1.5rem", marginBottom: 20 }}>Nuevas competiciones</h2>
            <div style={{ background: "var(--secondary-color)", borderRadius: 12, border: "1px solid rgba(255,255,255,0.05)", overflow: "hidden" }}>
              {recent.map((comp) => (
                <Link
                  key={comp.id}
                  to={`/competicion/${comp.id}`}
                  style={{
                    display: "flex",
                    alignItems: "center",
                    gap: 12,
                    padding: "12px 15px",
                    borderBottom: "1px solid rgba(255,255,255,0.05)",
                    textDecoration: "none",
                    color: "inherit",
                  }}
                >
                  {comp.logo_url ? (
                    <img src={comp.logo_url} style={{ width: 32, height: 32, objectFit: "contain" }} alt="" />
                  ) : (
                    <div
                      style={{
                        width: 32,
                        height: 32,
                        background: "rgba(255,255,255,0.1)",
                        borderRadius: "50%",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "center",
                        fontSize: "0.8rem",
                      }}
                    >
                      🏆
                    </div>
                  )}
                  <div>
                    <div style={{ fontWeight: 600, fontSize: "0.95rem", color: "#fff" }}>{comp.nombre}</div>
                    <div style={{ fontSize: "0.75rem", color: "var(--text-muted)" }}>{comp.temporada_actual}</div>
                  </div>
                </Link>
              ))}
              <Link to="/admin/competiciones" style={{ display: "block", textAlign: "center", padding: 12, fontSize: "0.85rem", color: "var(--accent-color)" }}>
                Ver todas
              </Link>
            </div>

            <div className="admin-card" style={{ marginTop: 25, borderLeft: "4px solid #ef4444" }}>
              <h2 style={{ fontSize: "1.25rem", marginBottom: 15 }}>Mantenimiento</h2>
              <div style={{ color: "var(--text-muted)", fontSize: "0.9rem", marginBottom: 15 }}>
                Elimina únicamente las fotos de los jugadores (campo foto_url).
              </div>
              <button
                type="button"
                className="btn-admin"
                style={{ marginLeft: 0, background: "#ef4444", border: "none", cursor: "pointer" }}
                onClick={() =>
                  run("/api/admin/maintenance/cleanup-player-photos", "Esto eliminará todas las fotos de jugadores. ¿Continuar?")
                }
              >
                Ejecutar limpieza
              </button>

              <div style={{ marginTop: 14, paddingTop: 14, borderTop: "1px solid rgba(255,255,255,0.06)" }}>
                <div style={{ color: "var(--text-muted)", fontSize: "0.9rem", marginBottom: 12 }}>Elimina la competición «Copa del Rey».</div>
                <button
                  type="button"
                  className="btn-admin"
                  style={{ marginLeft: 0, background: "rgba(255,255,255,0.05)", color: "var(--text-color)", border: "1px solid rgba(255,255,255,0.12)", cursor: "pointer" }}
                  onClick={() => run("/api/admin/maintenance/delete-copa-del-rey", "Esto eliminará la competición Copa del Rey. ¿Continuar?")}
                >
                  Eliminar Copa del Rey
                </button>
              </div>

              <div style={{ marginTop: 14, paddingTop: 14, borderTop: "1px solid rgba(255,255,255,0.06)" }}>
                <div style={{ color: "var(--text-muted)", fontSize: "0.9rem", marginBottom: 12 }}>
                  Elimina todos los equipos con 0 jugadores en plantilla y ajusta el AUTO_INCREMENT.
                </div>
                <button
                  type="button"
                  className="btn-admin"
                  style={{ marginLeft: 0, background: "rgba(255,255,255,0.05)", color: "var(--text-color)", border: "1px solid rgba(255,255,255,0.12)", cursor: "pointer" }}
                  onClick={() =>
                    run("/api/admin/maintenance/delete-empty-teams", "Esto eliminará TODOS los equipos sin jugadores. ¿Continuar?")
                  }
                >
                  Borrar equipos sin plantilla
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Layout>
  );
}
