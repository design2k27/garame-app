import type { ApiErrorBody } from "./types";

const DEFAULT_API_BASE_URL = "https://garame-api.103.13.211.56.nip.io";
const API_BASE_URL =
  (import.meta.env.VITE_API_BASE_URL as string | undefined) ?? DEFAULT_API_BASE_URL;

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly details?: Record<string, string>,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export function getApiBaseUrl() {
  return API_BASE_URL.replace(/\/$/, "");
}

interface ApiFetchOptions extends RequestInit {
  token?: string | null;
}

export async function apiFetch<T>(path: string, options: ApiFetchOptions = {}): Promise<T> {
  const headers = new Headers(options.headers);
  const hasBody = options.body !== undefined;

  if (hasBody && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  if (options.token) {
    headers.set("Authorization", `Bearer ${options.token}`);
  }

  const response = await fetch(`${getApiBaseUrl()}${path}`, {
    ...options,
    headers,
  });

  const contentType = response.headers.get("Content-Type") ?? "";
  const isJson = contentType.includes("application/json");
  const body = isJson ? await response.json() : await response.text();

  if (!response.ok) {
    const apiBody = body as ApiErrorBody;
    const error = apiBody.error;
    throw new ApiError(
      response.status,
      error?.code ?? "http_error",
      error?.message ?? `Erreur HTTP ${response.status}`,
      error?.details,
    );
  }

  return body as T;
}

export function getApiErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    if (error.details && Object.keys(error.details).length > 0) {
      return Object.values(error.details).join(" ");
    }
    return error.message;
  }

  if (error instanceof Error) {
    return error.message;
  }

  return "Une erreur inattendue est survenue.";
}
