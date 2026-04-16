import { useEffect, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { Layout } from "../layout/Layout";
import { apiGet } from "../api";

export function Buscar() {
  const [sp] = useSearchParams();
  const q = (sp.get("q") ?? "").trim();
  const [equipos, setEquipos] = useState<Record<string, unknown>[]>([]);
  const [jugadores, setJugadores] = useState<Record<string, unknown>[]>([]);
  const [competiciones, setCompeticiones] = useState<Record<string, unknown>[]>([]);

  useEffect(() => {
    if (!q) {
      setEquipos([]);
      setJugadores([]);
      setCompeticiones([]);
      return;
    }
    let cancelled = false;
    apiGet<{ equipos: Record<string, unknown>[]; jugadores: Record<string, unknown>[]; competiciones: Record<string, unknown>[] }>(
      `/api/search?q=${encodeURIComponent(q)}`
    )
      .then((d) => {
        if (!cancelled) {
          setEquipos(d.equipos);
          setJugadores(d.jugadores);
          setCompeticiones(d.competiciones);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setEquipos([]);
          setJugadores([]);
          setCompeticiones([]);
        }
      });
    return () => {
      cancelled = true;
    };
  }, [q]);

  const hasResults = q !== "" && (equipos.length > 0 || jugadores.length > 0 || competiciones.length > 0);

  return (
    <Layout title="Búsqueda">
      <div className="container search-shell">
        <h1 className="search-title">
          Búsqueda{q ? (
            <>
              : <span className="search-query">{q}</span>
            </>
          ) : null}
        </h1>

        {q === "" ? (
          <p className="search-lead">Escribe en el buscador del menú o en la página de inicio para ver equipos, jugadores y competiciones.</p>
        ) : !hasResults ? (
          <p className="search-lead">
            No hay resultados para «{q}». Prueba con otro nombre o revisa la ortografía.
          </p>
        ) : (
          <div className="search-columns">
            {equipos.length > 0 ? (
              <section>
                <h2 className="search-col-title">Equipos</h2>
                <div className="search-list">
                  {equipos.map((eq) => {
                    const e = eq as Record<string, unknown>;
                    return (
                      <Link key={String(e.id)} className="search-row" to={`/equipo/${e.id}`}>
                        <span className="search-row-thumb">
                          {e.escudo_url ? <img src={String(e.escudo_url)} alt="" /> : <span aria-hidden="true">🛡️</span>}
                        </span>
                        <div className="search-row-body">
                          <strong>{String(e.nombre)}</strong>
                        </div>
                      </Link>
                    );
                  })}
                </div>
              </section>
            ) : null}

            {jugadores.length > 0 ? (
              <section>
                <h2 className="search-col-title">Jugadores</h2>
                <div className="search-list">
                  {jugadores.map((jug) => {
                    const j = jug as Record<string, unknown>;
                    const tid = j.equipo_actual_id != null ? Number(j.equipo_actual_id) : 0;
                    const body = (
                      <>
                        <span className="search-row-thumb round">
                          {j.foto_url ? <img src={String(j.foto_url)} alt="" /> : <span aria-hidden="true">👤</span>}
                        </span>
                        <div className="search-row-body">
                          <strong>{String(j.nombre)}</strong>
                          <div className="search-row-sub">
                            {String(j.equipo_nombre ?? "Sin equipo")}
                            {j.posicion ? <> · {String(j.posicion)}</> : null}
                            {tid > 0 ? <span className="search-row-hint"> · Plantilla</span> : null}
                          </div>
                        </div>
                      </>
                    );
                    return tid > 0 ? (
                      <Link key={String(j.id)} className="search-row" to={`/equipo/${tid}`}>
                        {body}
                      </Link>
                    ) : (
                      <div key={String(j.id)} className="search-row search-row-static">
                        {body}
                      </div>
                    );
                  })}
                </div>
              </section>
            ) : null}

            {competiciones.length > 0 ? (
              <section>
                <h2 className="search-col-title">Competiciones</h2>
                <div className="search-list">
                  {competiciones.map((c) => {
                    const row = c as Record<string, unknown>;
                    return (
                      <Link key={String(row.id)} className="search-row" to={`/competicion/${row.id}`}>
                        <span className="search-row-thumb">
                          {row.logo_url ? <img src={String(row.logo_url)} alt="" /> : <span aria-hidden="true">🏆</span>}
                        </span>
                        <div className="search-row-body">
                          <strong>{String(row.nombre)}</strong>
                          <div className="search-row-sub">{String(row.temporada_actual ?? "")}</div>
                        </div>
                      </Link>
                    );
                  })}
                </div>
              </section>
            ) : null}
          </div>
        )}
      </div>
    </Layout>
  );
}
