import { useEffect, useState, type FormEvent } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { Layout } from "../../layout/Layout";

export function AdminLogin() {
  const navigate = useNavigate();
  const location = useLocation();
  const from = (location.state as { from?: string } | null)?.from ?? "/admin";

  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [noLogin, setNoLogin] = useState(false);

  useEffect(() => {
    let cancelled = false;
    fetch("/api/auth/config", { credentials: "include" })
      .then((r) => r.json())
      .then((d: { requiresLogin?: boolean }) => {
        if (cancelled) return;
        if (!d.requiresLogin) setNoLogin(true);
      })
      .catch(() => {
        if (!cancelled) setNoLogin(true);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    let cancelled = false;
    fetch("/api/auth/me", { credentials: "include" }).then((r) => {
      if (!cancelled && r.ok) navigate(from, { replace: true });
    });
    return () => {
      cancelled = true;
    };
  }, [from, navigate]);

  const onSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      const res = await fetch("/api/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({ username, password }),
      });
      const data = (await res.json().catch(() => ({}))) as { error?: string };
      if (!res.ok) {
        setError(data.error ?? res.statusText);
        return;
      }
      navigate(from, { replace: true });
    } catch {
      setError("No se pudo conectar con el servidor.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Layout title="Acceso administración">
      <div className="container page-shell" style={{ maxWidth: 440, marginTop: 48 }}>
        <header className="page-hero page-hero--tight" style={{ marginBottom: 24 }}>
          <div className="page-hero-main">
            <div className="page-hero-logo" aria-hidden="true">
              <span className="page-hero-icon">🔐</span>
            </div>
            <div>
              <h1 className="page-hero-title">Acceso admin</h1>
              <p className="page-hero-meta">Introduce usuario y contraseña configurados en el servidor.</p>
            </div>
          </div>
        </header>

        {noLogin ? (
          <div className="message-panel" style={{ marginBottom: 20 }}>
            El login por formulario no está activo. Define <code>JWT_SECRET</code>, <code>AUTH_USER</code> y <code>AUTH_PASSWORD</code> en{" "}
            <code>api/.env</code>, o usa la cabecera <code>x-admin-key</code> si solo tienes <code>ADMIN_KEY</code>.
          </div>
        ) : null}

        <div className="admin-card" style={{ padding: 28 }}>
          <form onSubmit={onSubmit} style={{ display: "flex", flexDirection: "column", gap: 16 }}>
            <label>
              <span style={{ display: "block", color: "var(--text-muted)", marginBottom: 6 }}>Usuario</span>
              <input
                className="form-control-admin"
                name="username"
                autoComplete="username"
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                required
              />
            </label>
            <label>
              <span style={{ display: "block", color: "var(--text-muted)", marginBottom: 6 }}>Contraseña</span>
              <input
                className="form-control-admin"
                name="password"
                type="password"
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
              />
            </label>
            {error ? (
              <div className="message-panel" style={{ margin: 0 }}>
                {error}
              </div>
            ) : null}
            <button type="submit" className="btn-admin" style={{ margin: 0, marginTop: 8 }} disabled={busy}>
              {busy ? "Entrando…" : "Entrar"}
            </button>
          </form>
        </div>

        <p style={{ marginTop: 24, textAlign: "center" }}>
          <Link to="/">Volver a la web</Link>
        </p>
      </div>
    </Layout>
  );
}
