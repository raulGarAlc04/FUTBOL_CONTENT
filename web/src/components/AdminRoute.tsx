import { useEffect, useState } from "react";
import { Navigate, Outlet, useLocation } from "react-router-dom";
import { Layout } from "../layout/Layout";

type AuthConfig = { requiresLogin: boolean };

export function AdminRoute() {
  const location = useLocation();
  const [phase, setPhase] = useState<"loading" | "ok" | "login">("loading");

  useEffect(() => {
    let cancelled = false;
    const run = async () => {
      try {
        const cfgRes = await fetch("/api/auth/config", { credentials: "include" });
        const cfg = (await cfgRes.json()) as AuthConfig;
        if (cancelled) return;
        if (!cfg.requiresLogin) {
          setPhase("ok");
          return;
        }
        const meRes = await fetch("/api/auth/me", { credentials: "include" });
        if (cancelled) return;
        if (meRes.ok) setPhase("ok");
        else setPhase("login");
      } catch {
        if (!cancelled) setPhase("login");
      }
    };
    void run();
    return () => {
      cancelled = true;
    };
  }, [location.pathname]);

  if (phase === "loading") {
    return (
      <Layout title="Administración">
        <div className="container page-shell">
          <div className="message-panel">Comprobando sesión…</div>
        </div>
      </Layout>
    );
  }

  if (phase === "login") {
    return <Navigate to="/admin/login" replace state={{ from: location.pathname }} />;
  }

  return <Outlet />;
}
