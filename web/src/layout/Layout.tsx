import { useEffect, useState, type ReactNode } from "react";
import { Link, NavLink, useLocation } from "react-router-dom";

type Props = {
  title?: string;
  fullWidth?: boolean;
  children: ReactNode;
};

export function Layout({ title, fullWidth, children }: Props) {
  const [navOpen, setNavOpen] = useState(false);
  const location = useLocation();
  const isAdmin = location.pathname.startsWith("/admin");

  useEffect(() => {
    setNavOpen(false);
  }, [location.pathname]);

  useEffect(() => {
    document.body.classList.toggle("nav-open", navOpen);
    return () => document.body.classList.remove("nav-open");
  }, [navOpen]);

  useEffect(() => {
    document.title = title ? `${title} · Futbol Data` : "Futbol Data";
  }, [title]);

  return (
    <>
      <header className="site-header">
        <div className="header-container">
          <Link to="/" className="logo-area" aria-label="Inicio Futbol Data">
            <span className="logo-mark" aria-hidden="true" />
            <span className="logo-text">Futbol Data</span>
          </Link>

          <button
            type="button"
            className="nav-toggle"
            data-nav-toggle
            aria-expanded={navOpen}
            aria-controls="site-nav-drawer"
            onClick={() => setNavOpen((o) => !o)}
          >
            <span className="nav-toggle-bars" aria-hidden="true" />
            <span className="visually-hidden">Menú</span>
          </button>

          <div
            className={`nav-overlay${navOpen ? " is-open" : ""}`}
            data-nav-panel
            aria-hidden={!navOpen}
            onClick={() => setNavOpen(false)}
          />
          <nav
            className={`site-nav${navOpen ? " is-open" : ""}`}
            id="site-nav-drawer"
            data-nav-panel
            aria-label="Principal"
            onClick={(e) => {
              if ((e.target as HTMLElement).closest("a")) setNavOpen(false);
            }}
          >
            <ul className="nav-list">
              <li>
                <NavLink to="/" end>
                  Inicio
                </NavLink>
              </li>
              <li>
                <NavLink to="/ligas">Ligas</NavLink>
              </li>
              <ContinentLinks />
            </ul>
            <form className="nav-search" action="/buscar" method="get" role="search" onSubmit={() => setNavOpen(false)}>
              <label className="visually-hidden" htmlFor="nav-search-q">
                Buscar
              </label>
              <input id="nav-search-q" type="search" name="q" placeholder="Buscar…" autoComplete="off" maxLength={120} />
              <button type="submit" className="nav-search-btn" aria-label="Buscar">
                ⌕
              </button>
            </form>
          </nav>

          {isAdmin ? (
            <Link to="/" className="btn-admin btn-admin--ghost">
              Web
            </Link>
          ) : (
            <Link to="/admin" className="btn-admin">
              Admin
            </Link>
          )}
        </div>
      </header>

      <main className={fullWidth ? "main-full" : ""}>{children}</main>

      <footer className="site-footer">
        <div className="footer-inner container">
          <div className="footer-brand">
            <span className="footer-logo" aria-hidden="true" />
            <div>
              <strong>Futbol Data</strong>
              <p className="footer-tagline">Ligas, equipos y datos con una interfaz clara.</p>
            </div>
          </div>
          <nav className="footer-links" aria-label="Pie de página">
            <Link to="/">Inicio</Link>
            <Link to="/ligas">Ligas</Link>
            <Link to="/buscar">Buscar</Link>
            <Link to="/admin">Administración</Link>
          </nav>
          <p className="footer-copy">&copy; {new Date().getFullYear()} Futbol Data</p>
        </div>
      </footer>
    </>
  );
}

function ContinentLinks() {
  const [items, setItems] = useState<{ id: number; nombre: string }[]>([]);
  useEffect(() => {
    let cancelled = false;
    fetch("/api/continentes")
      .then((r) => r.json())
      .then((d) => {
        if (!cancelled) setItems(Array.isArray(d) ? d : []);
      })
      .catch(() => {
        if (!cancelled) setItems([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);
  return (
    <>
      {items.map((c) => (
        <li key={c.id}>
          <NavLink to={`/continente/${c.id}`}>{c.nombre}</NavLink>
        </li>
      ))}
    </>
  );
}
