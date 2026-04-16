import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Layout } from "../layout/Layout";
import { apiGet } from "../api";

type Comp = {
  id: number;
  nombre: string;
  temporada_actual: string;
  logo_url: string | null;
  bandera_url?: string | null;
  pais_nombre?: string | null;
};

export function Home() {
  const [stats, setStats] = useState({ equipos: 0, jugadores: 0, competiciones: 0 });
  const [comps, setComps] = useState<Comp[]>([]);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    Promise.all([apiGet<{ equipos: number; jugadores: number; competiciones: number }>("/api/stats"), apiGet<Comp[]>("/api/home/featured-leagues")])
      .then(([s, c]) => {
        if (!cancelled) {
          setStats(s);
          setComps(c);
        }
      })
      .catch((e: Error) => {
        if (!cancelled) setErr(e.message);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <Layout title="Inicio">
      <div className="homepage-hero">
        <div className="hero-content">
          <h1>Futbol Data</h1>
          <p>Explora ligas, equipos y jugadores con datos ordenados y claros.</p>
          <div className="hero-stats">
            <div className="stat-item">
              <span className="stat-number">{stats.equipos}</span>
              <span className="stat-label">Equipos</span>
            </div>
            <div className="stat-item">
              <span className="stat-number">{stats.jugadores}</span>
              <span className="stat-label">Jugadores</span>
            </div>
            <div className="stat-item">
              <span className="stat-number">{stats.competiciones}</span>
              <span className="stat-label">Competiciones</span>
            </div>
          </div>

          <form className="hero-search" action="/buscar" method="get" role="search">
            <label className="visually-hidden" htmlFor="hero-q">
              Buscar
            </label>
            <input id="hero-q" type="search" name="q" placeholder="Equipo, jugador o competición…" autoComplete="off" maxLength={120} />
            <button type="submit">Buscar</button>
          </form>
        </div>
      </div>

      <div className="container page-shell">
        {err ? <div className="message-panel">{err}</div> : null}
        <section>
          <div className="section-head">
            <h2 className="section-title">Ligas destacadas</h2>
            <Link className="link-all" to="/ligas">
              Ver todas
            </Link>
          </div>

          {comps.length === 0 && !err ? (
            <div className="message-panel">No hay ligas registradas.</div>
          ) : (
            <div className="competitions-grid">
              {comps.map((comp) => (
                <Link key={comp.id} to={`/competicion/${comp.id}`} className="comp-card">
                  <div className="comp-card-header">
                    {comp.logo_url ? (
                      <img src={comp.logo_url} className="comp-logo" alt="" />
                    ) : (
                      <div className="comp-logo-placeholder" aria-hidden="true">
                        🏆
                      </div>
                    )}
                  </div>
                  <div className="comp-card-body">
                    <h3>{comp.nombre}</h3>
                    <div className="comp-meta">
                      {comp.bandera_url ? <img src={comp.bandera_url} alt="" width={16} height={12} className="comp-flag" /> : null}
                      <span>{comp.pais_nombre ?? "Internacional"}</span>
                    </div>
                    <div className="comp-season">{comp.temporada_actual}</div>
                  </div>
                </Link>
              ))}
            </div>
          )}
        </section>
      </div>
    </Layout>
  );
}
