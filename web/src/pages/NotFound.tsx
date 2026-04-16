import { Link } from "react-router-dom";
import { Layout } from "../layout/Layout";

export function NotFound() {
  return (
    <Layout title="No encontrada">
      <div className="container page-shell">
        <div className="message-panel">
          <p>No existe esta ruta.</p>
          <p style={{ marginTop: 12 }}>
            <Link to="/">Volver al inicio</Link>
          </p>
        </div>
      </div>
    </Layout>
  );
}
