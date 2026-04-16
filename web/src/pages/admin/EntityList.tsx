import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Layout } from "../../layout/Layout";
import { apiGet } from "../../api";
import { AdminKeyBanner } from "./AdminKeyBanner";

type Props = {
  title: string;
  endpoint: string;
  back?: string;
};

export function EntityList({ title, endpoint, back = "/admin" }: Props) {
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    apiGet<Record<string, unknown>[]>(endpoint)
      .then((d) => {
        if (!cancelled) setRows(Array.isArray(d) ? d : []);
      })
      .catch((e: Error) => {
        if (!cancelled) setErr(e.message);
      });
    return () => {
      cancelled = true;
    };
  }, [endpoint]);

  const keys = rows[0] ? Object.keys(rows[0]).slice(0, 8) : [];

  return (
    <Layout title={title}>
      <div className="container" style={{ marginTop: 40, marginBottom: 60 }}>
        <AdminKeyBanner />
        <div style={{ marginBottom: 18 }}>
          <Link to={back} className="btn-admin" style={{ margin: 0 }}>
            ← Volver
          </Link>
        </div>
        <h1 style={{ marginBottom: 16 }}>{title}</h1>
        {err ? <div className="message-panel">{err}</div> : null}
        {rows.length === 0 && !err ? (
          <div className="message-panel">Sin datos.</div>
        ) : (
          <div className="admin-card" style={{ padding: 0, overflow: "hidden" }}>
            <div className="table-wrap">
              <table className="admin-table">
                <thead>
                  <tr>
                    {keys.map((k) => (
                      <th key={k}>{k}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {rows.map((r, i) => (
                    <tr key={i}>
                      {keys.map((k) => (
                        <td key={k}>{formatCell(r[k], k)}</td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>
    </Layout>
  );
}

function formatCell(v: unknown, key: string): string {
  if (v == null) return "";
  if (typeof v === "object") return JSON.stringify(v);
  if (key === "bandera_url" || key === "logo_url" || key === "escudo_url" || key === "foto_url") {
    const s = String(v);
    return s.length > 40 ? `${s.slice(0, 40)}…` : s;
  }
  return String(v);
}
