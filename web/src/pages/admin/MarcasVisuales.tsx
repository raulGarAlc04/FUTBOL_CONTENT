import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { Layout } from "../../layout/Layout";
import { apiDelete, apiGet, apiPost, apiPut } from "../../api";
import type { VisualMark } from "../../lib/visualMarks";
import { AdminKeyBanner } from "./AdminKeyBanner";

export function MarcasVisuales() {
  const { id } = useParams();
  const cid = Number(id);
  const [nombreComp, setNombreComp] = useState("");
  const [marks, setMarks] = useState<VisualMark[]>([]);
  const [err, setErr] = useState<string | null>(null);
  const [form, setForm] = useState({ nombre: "", color: "#ffffff", posiciones: "", orden: 0 });

  useEffect(() => {
    if (!Number.isFinite(cid) || cid <= 0) return;
    let cancelled = false;
    apiGet<{ comp: { nombre: string }; marcasVisuales: VisualMark[] }>(`/api/competiciones/${cid}`)
      .then((d) => {
        if (cancelled) return;
        setNombreComp(d.comp.nombre);
        setMarks(d.marcasVisuales ?? []);
        setErr(null);
      })
      .catch((e: Error) => {
        if (!cancelled) setErr(e.message);
      });
    return () => {
      cancelled = true;
    };
  }, [cid]);

  const add = async () => {
    try {
      const res = await apiPost<{ marks: VisualMark[] }>(`/api/admin/competiciones/${cid}/marcas-visuales`, form);
      setMarks(res.marks);
      setForm({ nombre: "", color: "#ffffff", posiciones: "", orden: 0 });
    } catch (e) {
      alert((e as Error).message);
    }
  };

  const remove = async (markId: string) => {
    if (!confirm("¿Eliminar esta marca?")) return;
    try {
      const res = await apiDelete<{ marks: VisualMark[] }>(`/api/admin/competiciones/${cid}/marcas-visuales/${encodeURIComponent(markId)}`);
      setMarks(res.marks);
    } catch (e) {
      alert((e as Error).message);
    }
  };

  const saveAll = async () => {
    try {
      const res = await apiPut<{ marks: VisualMark[] }>(`/api/admin/competiciones/${cid}/marcas-visuales`, { marks });
      setMarks(res.marks);
      alert("Guardado");
    } catch (e) {
      alert((e as Error).message);
    }
  };

  return (
    <Layout title={`Marcas · ${nombreComp || cid}`}>
      <div className="container" style={{ marginTop: 40, marginBottom: 60 }}>
        <AdminKeyBanner />
        <div style={{ display: "flex", gap: 12, flexWrap: "wrap", marginBottom: 18 }}>
          <Link to="/admin" className="btn-admin" style={{ margin: 0 }}>
            ← Panel
          </Link>
          <Link to={`/competicion/${cid}`} className="btn-admin" style={{ margin: 0 }}>
            Ver competición
          </Link>
        </div>
        <h1 style={{ marginBottom: 8 }}>Marcas visuales</h1>
        <p style={{ color: "var(--text-muted)", marginBottom: 22 }}>{nombreComp}</p>
        {err ? <div className="message-panel">{err}</div> : null}

        <div className="admin-card" style={{ padding: 20, marginBottom: 20 }}>
          <h2 style={{ fontSize: "1.1rem", marginBottom: 12 }}>Añadir marca</h2>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(160px, 1fr))", gap: 12 }}>
            <label>
              <span style={{ display: "block", color: "var(--text-muted)", marginBottom: 6 }}>Nombre</span>
              <input className="form-control-admin" value={form.nombre} onChange={(e) => setForm({ ...form, nombre: e.target.value })} />
            </label>
            <label>
              <span style={{ display: "block", color: "var(--text-muted)", marginBottom: 6 }}>Color</span>
              <input className="form-control-admin" type="color" value={form.color} onChange={(e) => setForm({ ...form, color: e.target.value })} />
            </label>
            <label>
              <span style={{ display: "block", color: "var(--text-muted)", marginBottom: 6 }}>Posiciones (p. ej. 1-4,5)</span>
              <input className="form-control-admin" value={form.posiciones} onChange={(e) => setForm({ ...form, posiciones: e.target.value })} />
            </label>
            <label>
              <span style={{ display: "block", color: "var(--text-muted)", marginBottom: 6 }}>Orden</span>
              <input
                className="form-control-admin"
                type="number"
                value={form.orden}
                onChange={(e) => setForm({ ...form, orden: Number(e.target.value) })}
              />
            </label>
          </div>
          <button type="button" className="btn-admin" style={{ marginTop: 14 }} onClick={add}>
            Añadir
          </button>
        </div>

        <div className="admin-card" style={{ padding: 0, overflow: "hidden", marginBottom: 16 }}>
          <div className="table-wrap">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th>Color</th>
                  <th>Posiciones</th>
                  <th>Orden</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {marks.map((m) => (
                  <tr key={m.id}>
                    <td>
                      <input className="form-control-admin" value={m.nombre} onChange={(e) => setMarks(marks.map((x) => (x.id === m.id ? { ...x, nombre: e.target.value } : x)))} />
                    </td>
                    <td>
                      <input
                        className="form-control-admin"
                        type="color"
                        value={m.color}
                        onChange={(e) => setMarks(marks.map((x) => (x.id === m.id ? { ...x, color: e.target.value } : x)))}
                      />
                    </td>
                    <td>
                      <input
                        className="form-control-admin"
                        value={m.posiciones}
                        onChange={(e) => setMarks(marks.map((x) => (x.id === m.id ? { ...x, posiciones: e.target.value } : x)))}
                      />
                    </td>
                    <td style={{ maxWidth: 100 }}>
                      <input
                        className="form-control-admin"
                        type="number"
                        value={m.orden}
                        onChange={(e) => setMarks(marks.map((x) => (x.id === m.id ? { ...x, orden: Number(e.target.value) } : x)))}
                      />
                    </td>
                    <td>
                      <button type="button" className="btn-admin" style={{ margin: 0, background: "#ef4444" }} onClick={() => remove(m.id)}>
                        Eliminar
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        <button type="button" className="btn-admin" onClick={saveAll}>
          Guardar cambios
        </button>
      </div>
    </Layout>
  );
}
