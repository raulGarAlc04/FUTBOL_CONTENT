function adminHeaders(): Record<string, string> {
  const k = localStorage.getItem("fc_admin_key");
  return k ? { "x-admin-key": k } : {};
}

async function parseJson<T>(res: Response): Promise<T> {
  const text = await res.text();
  try {
    return JSON.parse(text) as T;
  } catch {
    throw new Error(text || res.statusText);
  }
}

const fetchDefaults: RequestInit = { credentials: "include" };

export async function apiGet<T>(path: string): Promise<T> {
  const res = await fetch(path, { ...fetchDefaults, headers: { ...adminHeaders() } });
  if (!res.ok) {
    const err = await parseJson<{ error?: string }>(res).catch(() => ({}) as { error?: string });
    throw new Error(err.error ?? res.statusText);
  }
  return parseJson<T>(res);
}

export async function apiPost<T>(path: string, body?: unknown): Promise<T> {
  const res = await fetch(path, {
    ...fetchDefaults,
    method: "POST",
    headers: { "Content-Type": "application/json", ...adminHeaders() },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  if (!res.ok) {
    const err = await parseJson<{ error?: string }>(res).catch(() => ({}) as { error?: string });
    throw new Error(err.error ?? res.statusText);
  }
  return parseJson<T>(res);
}

export async function apiPut<T>(path: string, body: unknown): Promise<T> {
  const res = await fetch(path, {
    ...fetchDefaults,
    method: "PUT",
    headers: { "Content-Type": "application/json", ...adminHeaders() },
    body: JSON.stringify(body),
  });
  if (!res.ok) {
    const err = await parseJson<{ error?: string }>(res).catch(() => ({}) as { error?: string });
    throw new Error(err.error ?? res.statusText);
  }
  return parseJson<T>(res);
}

export async function apiDelete<T>(path: string): Promise<T> {
  const res = await fetch(path, { ...fetchDefaults, method: "DELETE", headers: { ...adminHeaders() } });
  if (!res.ok) {
    const err = await parseJson<{ error?: string }>(res).catch(() => ({}) as { error?: string });
    throw new Error(err.error ?? res.statusText);
  }
  return parseJson<T>(res);
}
