import { Navigate, Route, Routes } from "react-router-dom";
import { AdminRoute } from "./components/AdminRoute";
import { NotFound } from "./pages/NotFound";
import { Home } from "./pages/Home";
import { Ligas } from "./pages/Ligas";
import { Competicion } from "./pages/Competicion";
import { Equipo } from "./pages/Equipo";
import { Continente } from "./pages/Continente";
import { Pais } from "./pages/Pais";
import { Buscar } from "./pages/Buscar";
import { AdminDashboard } from "./pages/admin/Dashboard";
import { AdminLogin } from "./pages/admin/AdminLogin";
import { EntityList } from "./pages/admin/EntityList";
import { MarcasVisuales } from "./pages/admin/MarcasVisuales";

export function App() {
  return (
    <Routes>
      <Route path="/" element={<Home />} />
      <Route path="/ligas" element={<Ligas />} />
      <Route path="/competicion/:id" element={<Competicion />} />
      <Route path="/equipo/:id" element={<Equipo />} />
      <Route path="/continente/:id" element={<Continente />} />
      <Route path="/pais/:id" element={<Pais />} />
      <Route path="/buscar" element={<Buscar />} />
      <Route path="/admin/login" element={<AdminLogin />} />
      <Route path="/admin" element={<AdminRoute />}>
        <Route index element={<AdminDashboard />} />
        <Route path="continentes" element={<EntityList title="Continentes" endpoint="/api/admin/continentes" />} />
        <Route path="paises" element={<EntityList title="Países" endpoint="/api/admin/paises" />} />
        <Route path="competiciones" element={<EntityList title="Competiciones" endpoint="/api/admin/competiciones" />} />
        <Route path="equipos" element={<EntityList title="Equipos" endpoint="/api/admin/equipos" />} />
        <Route path="competicion/:id/marcas" element={<MarcasVisuales />} />
      </Route>
      <Route path="/index.php" element={<Navigate to="/" replace />} />
      <Route path="*" element={<NotFound />} />
    </Routes>
  );
}
