import { useState } from "react";

export function AdminKeyBanner() {
  const [open, setOpen] = useState(false);
  const [val, setVal] = useState(() => localStorage.getItem("fc_admin_key") ?? "");

  const save = () => {
    const t = val.trim();
    if (t) localStorage.setItem("fc_admin_key", t);
    else localStorage.removeItem("fc_admin_key");
    setOpen(false);
    window.location.reload();
  };

  return (
    <div className="admin-card" style={{ padding: "14px 18px", marginBottom: 18 }}>
      <div style={{ display: "flex", gap: 12, alignItems: "center", flexWrap: "wrap", justifyContent: "space-between" }}>
        <div style={{ color: "var(--text-muted)", fontSize: "0.9rem" }}>
          Si configuraste <code style={{ color: "var(--accent-color)" }}>ADMIN_KEY</code> en el servidor, introdúcela aquí para usar el panel.
        </div>
        {!open ? (
          <button type="button" className="btn-admin" style={{ margin: 0 }} onClick={() => setOpen(true)}>
            Configurar clave
          </button>
        ) : (
          <div style={{ display: "flex", gap: 8, flexWrap: "wrap", alignItems: "center" }}>
            <input
              className="form-control-admin"
              style={{ minWidth: 220 }}
              type="password"
              value={val}
              onChange={(e) => setVal(e.target.value)}
              placeholder="ADMIN_KEY"
            />
            <button type="button" className="btn-admin" style={{ margin: 0 }} onClick={save}>
              Guardar
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
