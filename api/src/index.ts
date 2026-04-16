import "dotenv/config";
import express from "express";
import cors from "cors";
import cookieParser from "cookie-parser";
import path from "node:path";
import { fileURLToPath } from "node:url";
import type { ResultSetHeader, RowDataPacket } from "mysql2";
import { getPool } from "./db.js";
import { sortClasificacion, type ClasRow } from "./clasificacion.js";
import * as marks from "./marks.js";
import * as auth from "./auth.js";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
process.chdir(path.resolve(__dirname, ".."));

const app = express();
const PORT = Number(process.env.PORT ?? 4000);

app.use(cors({ origin: true, credentials: true }));
app.use(cookieParser());
app.use(express.json({ limit: "2mb" }));

function requireAdmin(req: express.Request, res: express.Response, next: express.NextFunction) {
  const adminKey = process.env.ADMIN_KEY?.trim();
  if (adminKey && req.header("x-admin-key") === adminKey) {
    next();
    return;
  }
  const session = auth.verifySessionToken(req.cookies?.[auth.SESSION_COOKIE]);
  if (session?.role === "admin") {
    next();
    return;
  }
  const cookieLogin = auth.isCookieLoginConfigured();
  if (cookieLogin || adminKey) {
    res.status(401).json({ error: "No autorizado" });
    return;
  }
  next();
}

app.get("/api/auth/config", (_req, res) => {
  res.json({
    requiresLogin: auth.isCookieLoginConfigured(),
  });
});

app.get("/api/auth/me", (req, res) => {
  const session = auth.verifySessionToken(req.cookies?.[auth.SESSION_COOKIE]);
  if (session?.role === "admin") {
    res.json({ authenticated: true, user: { role: "admin" } });
    return;
  }
  res.status(401).json({ authenticated: false });
});

app.post("/api/auth/login", (req, res) => {
  if (!auth.isCookieLoginConfigured()) {
    res.status(503).json({ error: "Login no configurado (JWT_SECRET, AUTH_USER, AUTH_PASSWORD en api/.env)" });
    return;
  }
  const body = req.body as { username?: string; password?: string };
  const username = String(body.username ?? "");
  const password = String(body.password ?? "");
  if (!auth.credentialsMatch(username, password)) {
    res.status(401).json({ error: "Usuario o contraseña incorrectos" });
    return;
  }
  try {
    const token = auth.signSessionToken();
    res.cookie(auth.SESSION_COOKIE, token, {
      httpOnly: true,
      secure: process.env.NODE_ENV === "production",
      sameSite: "lax",
      maxAge: 7 * 24 * 60 * 60 * 1000,
      path: "/",
    });
    res.json({ ok: true });
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: "Error al iniciar sesión" });
  }
});

app.post("/api/auth/logout", (_req, res) => {
  res.clearCookie(auth.SESSION_COOKIE, { path: "/" });
  res.json({ ok: true });
});

app.get("/api/health", (_req, res) => {
  res.json({ ok: true });
});

app.get("/api/stats", async (_req, res) => {
  try {
    const p = getPool();
    const [t1, t2, t3] = await Promise.all([
      p.query("SELECT COUNT(*) AS c FROM equipo"),
      p.query("SELECT COUNT(*) AS c FROM jugadores"),
      p.query("SELECT COUNT(*) AS c FROM competicion"),
    ]);
    const teams = t1[0] as RowDataPacket[];
    const players = t2[0] as RowDataPacket[];
    const comps = t3[0] as RowDataPacket[];
    res.json({
      equipos: Number(teams[0]?.c ?? 0),
      jugadores: Number(players[0]?.c ?? 0),
      competiciones: Number(comps[0]?.c ?? 0),
    });
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: "Error de base de datos" });
  }
});

app.get("/api/continentes", async (_req, res) => {
  try {
    const [rows] = await getPool().query("SELECT * FROM continente ORDER BY nombre ASC");
    res.json(rows);
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: "Error de base de datos" });
  }
});

app.get("/api/home/featured-leagues", async (_req, res) => {
  try {
    const [rows] = await getPool().query(
      `SELECT c.*, tc.nombre AS tipo_nombre, p.bandera_url, p.nombre AS pais_nombre
       FROM competicion c
       JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id
       LEFT JOIN pais p ON c.pais_id = p.id
       WHERE tc.nombre = 'Liga'
       ORDER BY c.id ASC
       LIMIT 12`
    );
    res.json(rows);
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: "Error de base de datos" });
  }
});

app.get("/api/competiciones", async (_req, res) => {
  try {
    const [rows] = await getPool().query(
      `SELECT c.*, tc.nombre AS tipo_nombre, p.bandera_url, p.nombre AS pais_nombre
       FROM competicion c
       JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id
       LEFT JOIN pais p ON c.pais_id = p.id
       ORDER BY tc.nombre ASC, c.nombre ASC`
    );
    res.json(rows);
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: "Error de base de datos" });
  }
});

app.get("/api/competiciones/:id", async (req, res) => {
  const id = Number(req.params.id);
  if (!Number.isFinite(id) || id <= 0) {
    res.status(400).json({ error: "ID inválido" });
    return;
  }
  try {
    const pool = getPool();
    const [compRows] = await pool.query(
      `SELECT c.*, tc.nombre AS tipo_nombre
       FROM competicion c
       JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id
       WHERE c.id = ?`,
      [id]
    );
    const comp = (compRows as RowDataPacket[])[0] as
      | {
          id: number;
          nombre: string;
          temporada_actual: string;
          logo_url: string | null;
          tipo_nombre: string;
        }
      | undefined;
    if (!comp) {
      res.status(404).json({ error: "No encontrada" });
      return;
    }
    const [clasRaw] = await pool.query(
      `SELECT e.id AS equipo_id, e.nombre, e.escudo_url,
              COALESCE(c.pj, 0) AS pj, COALESCE(c.pg, 0) AS pg, COALESCE(c.pe, 0) AS pe, COALESCE(c.pp, 0) AS pp,
              COALESCE(c.gf, 0) AS gf, COALESCE(c.gc, 0) AS gc, COALESCE(c.puntos, 0) AS puntos
       FROM competicion_equipo ce
       JOIN equipo e ON ce.equipo_id = e.id
       LEFT JOIN clasificacion c ON c.competicion_id = ce.competicion_id AND c.equipo_id = e.id
       WHERE ce.competicion_id = ?`,
      [id]
    );
    const clasificacion = sortClasificacion(
      (clasRaw as ClasRow[]).map((r) => ({
        ...r,
        pj: Number(r.pj),
        pg: Number(r.pg),
        pe: Number(r.pe),
        pp: Number(r.pp),
        gf: Number(r.gf),
        gc: Number(r.gc),
        puntos: Number(r.puntos),
        equipo_id: Number(r.equipo_id),
      }))
    );
    const [equipos] = await pool.query(
      `SELECT e.*, p.nombre AS pais_nombre, p.bandera_url
       FROM equipo e
       JOIN competicion_equipo ce ON e.id = ce.equipo_id
       LEFT JOIN pais p ON e.pais_id = p.id
       WHERE ce.competicion_id = ?
       ORDER BY e.nombre ASC`,
      [id]
    );
    const marcasVisuales = marks.getMarks(id);
    res.json({ comp, clasificacion, equipos, marcasVisuales });
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: "Error de base de datos" });
  }
});

app.get("/api/search", async (req, res) => {
  const q = String(req.query.q ?? "").trim();
  if (!q) {
    res.json({ equipos: [], jugadores: [], competiciones: [] });
    return;
  }
  const term = `%${q}%`;
  try {
    const pool = getPool();
    const [equipos] = await pool.query("SELECT * FROM equipo WHERE nombre LIKE ? ORDER BY nombre ASC LIMIT 12", [term]);
    const [jugadores] = await pool.query(
      `SELECT j.*, e.nombre AS equipo_nombre
       FROM jugadores j
       LEFT JOIN equipo e ON j.equipo_actual_id = e.id
       WHERE j.nombre LIKE ?
       ORDER BY j.nombre ASC
       LIMIT 12`,
      [term]
    );
    const [competiciones] = await pool.query(
      "SELECT * FROM competicion WHERE nombre LIKE ? ORDER BY nombre ASC LIMIT 12",
      [term]
    );
    res.json({ equipos, jugadores, competiciones });
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: "Error de base de datos" });
  }
});

const ORDER_JUG_POSICION = `FIELD(j.posicion, 'Portero', 'Defensa', 'Centrocampista', 'Delantero', 'Entrenador'), (j.dorsal IS NULL OR j.dorsal = 0), j.dorsal ASC, j.nombre ASC`;
const ORDER_JUG_DORSAL = `(j.dorsal IS NULL OR j.dorsal = 0), j.dorsal ASC, FIELD(j.posicion, 'Portero', 'Defensa', 'Centrocampista', 'Delantero', 'Entrenador'), j.nombre ASC`;

app.get("/api/equipo/:id", async (req, res) => {
  const id = Number(req.params.id);
  if (!Number.isFinite(id) || id <= 0) {
    res.status(400).json({ error: "ID inválido" });
    return;
  }
  const orden = String(req.query.orden_jugadores ?? "posicion") === "dorsal" ? "dorsal" : "posicion";
  try {
    const pool = getPool();
    const [equipoRows] = await pool.query(
      `SELECT e.*, p.nombre AS pais_nombre, p.bandera_url
       FROM equipo e
       LEFT JOIN pais p ON e.pais_id = p.id
       WHERE e.id = ?`,
      [id]
    );
    const equipo = (equipoRows as RowDataPacket[])[0] as Record<string, unknown> | undefined;
    if (!equipo) {
      res.status(404).json({ error: "No encontrado" });
      return;
    }
    const orderBy = orden === "dorsal" ? ORDER_JUG_DORSAL : ORDER_JUG_POSICION;
    const [jugadores] = await pool.query(
      `SELECT j.*, p.nombre AS pais_nombre, p.bandera_url
       FROM jugadores j
       LEFT JOIN pais p ON j.pais_id = p.id
       WHERE j.equipo_actual_id = ?
       ORDER BY ${orderBy}`,
      [id]
    );
    const [competiciones] = await pool.query(
      `SELECT c.id, c.nombre, c.temporada_actual, c.logo_url, tc.nombre AS tipo_nombre
       FROM competicion c
       JOIN competicion_equipo ce ON ce.competicion_id = c.id
       JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id
       WHERE ce.equipo_id = ?
       ORDER BY c.nombre ASC`,
      [id]
    );
    const comps = competiciones as { id: number }[];
    const clasificacionesPorCompeticion: Record<number, ClasRow[]> = {};
    for (const c of comps) {
      const compId = Number(c.id);
      const [rows] = await pool.query(
        `SELECT e.id AS equipo_id, e.nombre, e.escudo_url,
                COALESCE(cl.pj, 0) AS pj, COALESCE(cl.pg, 0) AS pg, COALESCE(cl.pe, 0) AS pe, COALESCE(cl.pp, 0) AS pp,
                COALESCE(cl.gf, 0) AS gf, COALESCE(cl.gc, 0) AS gc, COALESCE(cl.puntos, 0) AS puntos
         FROM competicion_equipo ce
         JOIN equipo e ON e.id = ce.equipo_id
         LEFT JOIN clasificacion cl ON cl.competicion_id = ce.competicion_id AND cl.equipo_id = e.id
         WHERE ce.competicion_id = ?`,
        [compId]
      );
      clasificacionesPorCompeticion[compId] = sortClasificacion(
        (rows as ClasRow[]).map((r) => ({
          ...r,
          pj: Number(r.pj),
          pg: Number(r.pg),
          pe: Number(r.pe),
          pp: Number(r.pp),
          gf: Number(r.gf),
          gc: Number(r.gc),
          puntos: Number(r.puntos),
          equipo_id: Number(r.equipo_id),
        }))
      );
    }
    const plantilla: Record<string, RowDataPacket[]> = {
      Portero: [],
      Defensa: [],
      Centrocampista: [],
      Delantero: [],
      Entrenador: [],
    };
    for (const j of jugadores as RowDataPacket[]) {
      let pos = String(j.posicion ?? "Centrocampista");
      if (!plantilla[pos]) pos = "Centrocampista";
      plantilla[pos].push(j);
    }
    res.json({ equipo, jugadores, orden_jugadores: orden, plantilla, competiciones, clasificacionesPorCompeticion });
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: "Error de base de datos" });
  }
});

app.get("/api/continente/:id", async (req, res) => {
  const id = Number(req.params.id);
  if (!Number.isFinite(id) || id <= 0) {
    res.status(400).json({ error: "ID inválido" });
    return;
  }
  try {
    const pool = getPool();
    const [contRows] = await pool.query("SELECT * FROM continente WHERE id = ?", [id]);
    const continente = (contRows as RowDataPacket[])[0];
    if (!continente) {
      res.status(404).json({ error: "No encontrado" });
      return;
    }
    const [paises] = await pool.query("SELECT * FROM pais WHERE continente_id = ? ORDER BY nombre ASC", [id]);
    res.json({ continente, paises });
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: "Error de base de datos" });
  }
});

app.get("/api/pais/:id", async (req, res) => {
  const id = Number(req.params.id);
  if (!Number.isFinite(id) || id <= 0) {
    res.status(400).json({ error: "ID inválido" });
    return;
  }
  const filtroEquipo = req.query.equipo_id !== undefined ? Number(req.query.equipo_id) : -1;
  const posicionesDisponibles = ["Portero", "Defensa", "Centrocampista", "Delantero", "Entrenador"];
  let filtroPosicion = String(req.query.posicion ?? "");
  if (!posicionesDisponibles.includes(filtroPosicion)) filtroPosicion = "";
  let ordenJugadores = String(req.query.orden_jugadores ?? "posicion");
  if (!["equipo", "dorsal", "posicion"].includes(ordenJugadores)) ordenJugadores = "posicion";

  let orderBy = `FIELD(j.posicion, 'Portero', 'Defensa', 'Centrocampista', 'Delantero', 'Entrenador'), (j.dorsal IS NULL OR j.dorsal = 0), j.dorsal ASC, j.nombre ASC`;
  if (ordenJugadores === "dorsal") {
    orderBy = `(j.dorsal IS NULL OR j.dorsal = 0), j.dorsal ASC, FIELD(j.posicion, 'Portero', 'Defensa', 'Centrocampista', 'Delantero', 'Entrenador'), j.nombre ASC`;
  } else if (ordenJugadores === "equipo") {
    orderBy = `(e.nombre IS NULL), e.nombre ASC, FIELD(j.posicion, 'Portero', 'Defensa', 'Centrocampista', 'Delantero', 'Entrenador'), (j.dorsal IS NULL OR j.dorsal = 0), j.dorsal ASC, j.nombre ASC`;
  }

  try {
    const pool = getPool();
    const [paisRows] = await pool.query("SELECT * FROM pais WHERE id = ?", [id]);
    const pais = (paisRows as RowDataPacket[])[0];
    if (!pais) {
      res.status(404).json({ error: "No encontrado" });
      return;
    }
    const [competiciones] = await pool.query(
      `SELECT c.*, tc.nombre AS tipo_nombre
       FROM competicion c
       JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id
       WHERE c.pais_id = ?
       ORDER BY c.nombre ASC`,
      [id]
    );
    const [equipos] = await pool.query(
      `SELECT e.id, e.nombre, e.escudo_url
       FROM equipo e
       WHERE e.pais_id = ?
       ORDER BY e.nombre ASC`,
      [id]
    );
    const [equiposJugadores] = await pool.query(
      `SELECT DISTINCT e.id, e.nombre
       FROM jugadores j
       JOIN equipo e ON e.id = j.equipo_actual_id
       WHERE j.pais_id = ?
       ORDER BY e.nombre ASC`,
      [id]
    );
    const [sinRows] = await pool.query(
      "SELECT COUNT(*) AS c FROM jugadores WHERE pais_id = ? AND equipo_actual_id IS NULL",
      [id]
    );
    const sinRow = (sinRows as RowDataPacket[])[0];
    const haySinEquipo = Number(sinRow?.c ?? 0) > 0;

    let where = "WHERE j.pais_id = ?";
    const params: unknown[] = [id];
    if (filtroEquipo === 0) {
      where += " AND j.equipo_actual_id IS NULL";
    } else if (filtroEquipo > 0) {
      where += " AND j.equipo_actual_id = ?";
      params.push(filtroEquipo);
    }
    if (filtroPosicion) {
      where += " AND j.posicion = ?";
      params.push(filtroPosicion);
    }
    const [jugadoresPais] = await pool.query(
      `SELECT j.id, j.nombre, j.posicion, j.dorsal, e.id AS equipo_id, e.nombre AS equipo_nombre, e.escudo_url AS equipo_escudo_url
       FROM jugadores j
       LEFT JOIN equipo e ON e.id = j.equipo_actual_id
       ${where}
       ORDER BY ${orderBy}`,
      params
    );

    res.json({
      pais,
      competiciones,
      equipos,
      equiposJugadores,
      haySinEquipo,
      jugadoresPais,
      filtros: { equipo_id: filtroEquipo, posicion: filtroPosicion, orden_jugadores: ordenJugadores },
    });
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: "Error de base de datos" });
  }
});

app.get("/api/admin/stats", requireAdmin, async (_req, res) => {
  try {
    const p = getPool();
    const [a1, a2, a3, a4, a5, a6, a7] = await Promise.all([
      p.query("SELECT COUNT(*) AS c FROM continente"),
      p.query("SELECT COUNT(*) AS c FROM pais"),
      p.query(
        `SELECT COUNT(*) AS c FROM competicion c
         JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id
         WHERE tc.nombre = 'Liga'`
      ),
      p.query("SELECT COUNT(*) AS c FROM equipo"),
      p.query("SELECT COUNT(*) AS c FROM jugadores"),
      p.query(
        `SELECT COUNT(*) AS c FROM equipo e
         LEFT JOIN competicion_equipo ce ON ce.equipo_id = e.id
         WHERE ce.equipo_id IS NULL`
      ),
      p.query(
        `SELECT COUNT(*) AS c FROM equipo e
         LEFT JOIN jugadores j ON j.equipo_actual_id = e.id
         WHERE j.id IS NULL`
      ),
    ]);
    const continentes = a1[0] as RowDataPacket[];
    const paises = a2[0] as RowDataPacket[];
    const ligas = a3[0] as RowDataPacket[];
    const equipos = a4[0] as RowDataPacket[];
    const jugadores = a5[0] as RowDataPacket[];
    const sinLiga = a6[0] as RowDataPacket[];
    const sinPlantilla = a7[0] as RowDataPacket[];
    res.json({
      continentes: Number(continentes[0]?.c ?? 0),
      paises: Number(paises[0]?.c ?? 0),
      ligas: Number(ligas[0]?.c ?? 0),
      equipos: Number(equipos[0]?.c ?? 0),
      jugadores: Number(jugadores[0]?.c ?? 0),
      equipos_sin_liga: Number(sinLiga[0]?.c ?? 0),
      equipos_sin_plantilla: Number(sinPlantilla[0]?.c ?? 0),
    });
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: "Error de base de datos" });
  }
});

app.get("/api/admin/active-leagues", requireAdmin, async (_req, res) => {
  try {
    const [rows] = await getPool().query(
      `SELECT c.id, c.nombre, c.temporada_actual, c.logo_url,
              (SELECT COUNT(*) FROM competicion_equipo ce WHERE ce.competicion_id = c.id) AS equipos_inscritos
       FROM competicion c
       JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id
       WHERE tc.nombre = 'Liga'
       ORDER BY c.id DESC
       LIMIT 12`
    );
    res.json(rows);
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: "Error de base de datos" });
  }
});

app.get("/api/admin/recent-leagues", requireAdmin, async (_req, res) => {
  try {
    const [rows] = await getPool().query(
      `SELECT c.*,
              (SELECT COUNT(*) FROM competicion_equipo ce WHERE ce.competicion_id = c.id) AS equipos_inscritos
       FROM competicion c
       JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id
       WHERE tc.nombre = 'Liga'
       ORDER BY c.id DESC
       LIMIT 5`
    );
    res.json(rows);
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: "Error de base de datos" });
  }
});

app.post("/api/admin/maintenance/cleanup-player-photos", requireAdmin, async (_req, res) => {
  try {
    await getPool().query("UPDATE jugadores SET foto_url = NULL");
    res.json({ ok: true });
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: String(e) });
  }
});

app.post("/api/admin/maintenance/delete-copa-del-rey", requireAdmin, async (_req, res) => {
  try {
    const pool = getPool();
    const [r1] = await pool.query("DELETE FROM competicion WHERE nombre = ?", ["Copa del Rey"]);
    let deleted = (r1 as ResultSetHeader).affectedRows ?? 0;
    if (deleted === 0) {
      const [r2] = await pool.query("DELETE FROM competicion WHERE nombre LIKE ?", ["%Copa del Rey%"]);
      deleted += (r2 as ResultSetHeader).affectedRows ?? 0;
    }
    res.json({ ok: true, deleted });
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: String(e) });
  }
});

app.post("/api/admin/maintenance/delete-empty-teams", requireAdmin, async (_req, res) => {
  const pool = getPool();
  const conn = await pool.getConnection();
  try {
    const [idsRows] = await conn.query(
      `SELECT e.id FROM equipo e
       LEFT JOIN jugadores j ON j.equipo_actual_id = e.id
       WHERE j.id IS NULL`
    );
    const ids = (idsRows as RowDataPacket[]).map((r) => Number(r.id)).filter((n) => n > 0);
    if (ids.length === 0) {
      res.json({ ok: true, deleted: 0 });
      conn.release();
      return;
    }
    const ph = ids.map(() => "?").join(",");
    await conn.beginTransaction();
    try {
      await conn.query(`DELETE FROM clasificacion WHERE equipo_id IN (${ph})`, ids);
    } catch {
      /* ignore */
    }
    try {
      await conn.query(`DELETE FROM competicion_equipo WHERE equipo_id IN (${ph})`, ids);
    } catch {
      /* ignore */
    }
    const [del] = await conn.query(`DELETE FROM equipo WHERE id IN (${ph})`, ids);
    const deleted = (del as ResultSetHeader).affectedRows ?? 0;
    await conn.commit();
    try {
      const [mxRows] = await pool.query("SELECT COALESCE(MAX(id), 0) + 1 AS n FROM equipo");
      const mx = (mxRows as RowDataPacket[])[0];
      const nextId = Math.max(1, Number(mx?.n ?? 1));
      await pool.query(`ALTER TABLE equipo AUTO_INCREMENT = ${nextId}`);
    } catch {
      /* ignore */
    }
    res.json({ ok: true, deleted });
  } catch (e) {
    await conn.rollback();
    console.error(e);
    res.status(500).json({ error: String(e) });
  } finally {
    conn.release();
  }
});

app.get("/api/admin/continentes", requireAdmin, async (_req, res) => {
  try {
    const [rows] = await getPool().query("SELECT * FROM continente ORDER BY nombre ASC");
    res.json(rows);
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: "Error de base de datos" });
  }
});

app.get("/api/admin/paises", requireAdmin, async (_req, res) => {
  try {
    const [rows] = await getPool().query(
      `SELECT p.*, c.nombre AS continente_nombre
       FROM pais p
       LEFT JOIN continente c ON c.id = p.continente_id
       ORDER BY p.nombre ASC`
    );
    res.json(rows);
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: "Error de base de datos" });
  }
});

app.get("/api/admin/competiciones", requireAdmin, async (_req, res) => {
  try {
    const [rows] = await getPool().query(
      `SELECT c.*, tc.nombre AS tipo_nombre, p.nombre AS pais_nombre
       FROM competicion c
       JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id
       LEFT JOIN pais p ON c.pais_id = p.id
       ORDER BY c.id DESC`
    );
    res.json(rows);
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: "Error de base de datos" });
  }
});

app.get("/api/admin/equipos", requireAdmin, async (_req, res) => {
  try {
    const [rows] = await getPool().query(
      `SELECT e.*, p.nombre AS pais_nombre
       FROM equipo e
       LEFT JOIN pais p ON p.id = e.pais_id
       ORDER BY e.nombre ASC`
    );
    res.json(rows);
  } catch (e) {
    console.error(e);
    res.status(500).json({ error: "Error de base de datos" });
  }
});

app.put("/api/admin/competiciones/:id/marcas-visuales", requireAdmin, async (req, res) => {
  const id = Number(req.params.id);
  if (!Number.isFinite(id) || id <= 0) {
    res.status(400).json({ error: "ID inválido" });
    return;
  }
  const body = req.body as { marks?: marks.VisualMark[] };
  if (!Array.isArray(body.marks)) {
    res.status(400).json({ error: "marks debe ser un array" });
    return;
  }
  const ok = marks.setMarks(
    id,
    body.marks.map((m) => ({
      id: String(m.id),
      nombre: String(m.nombre ?? ""),
      color: String(m.color ?? ""),
      posiciones: String(m.posiciones ?? ""),
      orden: Number(m.orden ?? 0) || 0,
    }))
  );
  if (!ok) {
    res.status(500).json({ error: "No se pudo guardar" });
    return;
  }
  res.json({ ok: true, marks: marks.getMarks(id) });
});

app.post("/api/admin/competiciones/:id/marcas-visuales", requireAdmin, async (req, res) => {
  const id = Number(req.params.id);
  if (!Number.isFinite(id) || id <= 0) {
    res.status(400).json({ error: "ID inválido" });
    return;
  }
  const body = req.body as { nombre?: string; color?: string; posiciones?: string; orden?: number };
  const nombre = String(body.nombre ?? "").trim();
  const posiciones = marks.normalizePosiciones(String(body.posiciones ?? ""));
  if (!nombre || !posiciones) {
    res.status(400).json({ error: "nombre y posiciones requeridos" });
    return;
  }
  const current = marks.getMarks(id);
  current.push({
    id: marks.newMarkId(),
    nombre,
    color: marks.normalizeColor(String(body.color ?? "")),
    posiciones,
    orden: Number(body.orden ?? 0) || 0,
  });
  marks.setMarks(id, current);
  res.json({ ok: true, marks: marks.getMarks(id) });
});

app.delete("/api/admin/competiciones/:id/marcas-visuales/:markId", requireAdmin, async (req, res) => {
  const id = Number(req.params.id);
  const markId = String(req.params.markId ?? "");
  if (!Number.isFinite(id) || id <= 0 || !markId) {
    res.status(400).json({ error: "Parámetros inválidos" });
    return;
  }
  const next = marks.getMarks(id).filter((m) => m.id !== markId);
  marks.setMarks(id, next);
  res.json({ ok: true, marks: marks.getMarks(id) });
});

app.listen(PORT, () => {
  console.log(`API listening on http://localhost:${PORT}`);
});
