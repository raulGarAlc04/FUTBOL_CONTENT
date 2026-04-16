import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Layout } from "../layout/Layout";
import { apiGet } from "../api";

type Comp = {
  id: number;
  nombre: string;
  temporada_actual: string;
  logo_url: string | null;
  tipo_nombre: string;
  bandera_url?: string | null;
  pais_nombre?: string | null;
};

export function Ligas() {
  const [porTipo, setPorTipo] = useState<Record<string, Comp[]>>({});
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    apiGet<Comp[]>("/api/competiciones")
      .then((list) => {
        if (cancelled) return;
        const m: Record<string, Comp[]> = {};
        for (const c of list) {
          const t = c.tipo_nombre || "Otros";
          if (!m[t]) m[t] = [];
          m[t].push(c);
        }
        setPorTipo(m);
      })
      .catch((e: Error) => {
        if (!cancelled) setErr(e.message);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <Layout title="Ligas y competiciones">
      <div className="container page-shell">
        <header className="page-hero page-hero--tight">
          <div className="page-hero-main">
            <div className="page-hero-logo" aria-hidden="true">
              <span className="page-hero-icon">🏆</span>
            </div>
            <div>
              <h1 className="page-hero-title">Ligas y competiciones</h1>
              <p className="page-hero-meta">Listado completo ordenado por tipo.</p>
            </div>
          </div>
        </header>

        {err ? <div className="message-panel">{err}</div> : null}

        {Object.keys(porTipo).length === 0 && !err ? (
          <div className="message-panel">No hay competiciones en la base de datos.</div>
        ) : (
          Object.entries(porTipo).map(([tipo, lista]) => (
            <section key={tipo} style={{ marginTop: 36 }}>
              <h2 className="section-title">{tipo}</h2>
              <div className="competitions-grid">
                {lista.map((comp) => (
                  <Link key={comp.id} to={`/competicion/${comp.id}`} className="comp-card">
                    <div className="comp-card-header">
                      {comp.logo_url ? (
                        <img src={comp.logo_url} className="comp-logo" alt="" />
                      ) : (
                        <div className="comp-logo-placeholder">🏆</div>
                      )}
                    </div>
                    <div className="comp-card-body">
                      <h3>{comp.nombre}</h3>
                      <div className="comp-meta">
                        {comp.bandera_url ? (
                          <img src={comp.bandera_url} alt="" width={16} height={12} style={{ width: 16, height: "auto", borderRadius: 2 }} />
                        ) : null}
                        <span>{comp.pais_nombre ?? "Internacional"}</span>
                      </div>
                      <div className="comp-season">{comp.temporada_actual}</div>
                    </div>
                  </Link>
                ))}
              </div>
            </section>
          ))
        )}
      </div>
    </Layout>
  );
}
