import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { Layout } from "../layout/Layout";
import { apiGet } from "../api";

export function Continente() {
  const { id } = useParams();
  const cid = Number(id);
  const [nombre, setNombre] = useState("");
  const [paises, setPaises] = useState<Record<string, unknown>[]>([]);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    if (!Number.isFinite(cid) || cid <= 0) {
      setErr("Continente no especificado.");
      return;
    }
    let cancelled = false;
    apiGet<{ continente: { nombre: string }; paises: Record<string, unknown>[] }>(`/api/continente/${cid}`)
      .then((d) => {
        if (cancelled) return;
        setNombre(d.continente.nombre);
        setPaises(d.paises);
      })
      .catch((e: Error) => {
        if (!cancelled) setErr(e.message);
      });
    return () => {
      cancelled = true;
    };
  }, [cid]);

  if (err && !nombre) {
    return (
      <Layout title="Continente">
        <div className="container page-shell">
          <div className="message-panel">{err}</div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout title={nombre || "Continente"}>
      <div className="container page-shell">
        <header className="page-hero page-hero--tight">
          <div className="page-hero-main">
            <div className="page-hero-logo" aria-hidden="true">
              <span className="page-hero-icon">🌍</span>
            </div>
            <div>
              <h1 className="page-hero-title">{nombre}</h1>
              <p className="page-hero-meta">Países y selecciones asociadas a este continente.</p>
            </div>
          </div>
        </header>
        {paises.length === 0 ? (
          <div className="message-panel">No hay países registrados en este continente todavía.</div>
        ) : (
          <div className="country-grid">
            {paises.map((p) => {
              const row = p as Record<string, string | number | null>;
              return (
                <Link key={String(row.id)} to={`/pais/${row.id}`} className="country-card">
                  {row.bandera_url ? <img className="country-card-flag" src={String(row.bandera_url)} alt="" width={64} height={40} /> : null}
                  <span className="country-card-name">{String(row.nombre)}</span>
                  {row.codigo_iso ? <span className="country-card-iso">{String(row.codigo_iso)}</span> : null}
                </Link>
              );
            })}
          </div>
        )}
      </div>
    </Layout>
  );
}
