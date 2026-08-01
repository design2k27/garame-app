import { apiFetch } from "./client";
import type {
  AuthResponse,
  GameFull,
  GameSummary,
  HistoryResponse,
  ListGamesResponse,
  PollResponse,
  ProfileStatsResponse,
  SingleGameResponse,
} from "./types";

export function login(email: string, password: string) {
  return apiFetch<AuthResponse>("/api/auth/login", {
    method: "POST",
    body: JSON.stringify({ email, password }),
  });
}

export function register(username: string, email: string, password: string) {
  return apiFetch<AuthResponse>("/api/auth/register", {
    method: "POST",
    body: JSON.stringify({ username, email, password }),
  });
}

export function getMe(token: string) {
  return apiFetch<{ user: AuthResponse["user"] }>("/api/auth/me", { token });
}

export function getProfileStats(token: string) {
  return apiFetch<ProfileStatsResponse>("/api/profile/stats", { token });
}

export function getHistory(token: string, page = 1, perPage = 10) {
  return apiFetch<HistoryResponse>(`/api/games/my/history?page=${page}&perPage=${perPage}`, {
    token,
  });
}

export function getOpenGames(token: string) {
  return apiFetch<ListGamesResponse>("/api/games/open", { token });
}

export function getMyActiveGames(token: string) {
  return apiFetch<ListGamesResponse>("/api/games/my/active", { token });
}

export function createGame(token: string) {
  return apiFetch<SingleGameResponse<GameSummary>>("/api/games", {
    method: "POST",
    token,
  });
}

export function joinGame(token: string, gameId: string) {
  return apiFetch<SingleGameResponse<GameSummary>>(`/api/games/${gameId}/join`, {
    method: "POST",
    token,
  });
}

export function startMatchmaking(token: string) {
  return apiFetch<SingleGameResponse<GameSummary>>(`/api/games/matchmaking`, {
    method: "POST",
    token,
  });
}

export function cancelMatchmaking(token: string) {
  return apiFetch<{ action: "cancelled"; gameId: string | null }>(
    "/api/games/matchmaking/cancel",
    {
      method: "POST",
      token,
    },
  );
}

export function getGame(token: string, gameId: string) {
  return apiFetch<SingleGameResponse<GameFull>>(`/api/games/${gameId}`, { token });
}

export function syncGame(token: string, gameId: string, sinceVersion?: number) {
  const query = sinceVersion ? `?sinceVersion=${sinceVersion}` : "";
  return apiFetch<{
    action: "synced";
    inSync: boolean;
    serverVersion: number;
    game: GameFull;
    result: SingleGameResponse<GameFull>["result"];
  }>(`/api/games/${gameId}/sync${query}`, { token });
}

export function pollGame(token: string, gameId: string, sinceVersion: number) {
  return apiFetch<PollResponse>(`/api/games/${gameId}/poll?sinceVersion=${sinceVersion}`, {
    token,
  });
}

export function ackGame(token: string, gameId: string, stateVersion: number) {
  return apiFetch<{
    action: "acked";
    gameId: string;
    ackedStateVersion: number;
    serverVersion: number;
    inSync: boolean;
  }>(`/api/games/${gameId}/ack`, {
    method: "POST",
    token,
    body: JSON.stringify({ stateVersion }),
  });
}

export function playCard(
  token: string,
  gameId: string,
  value: number,
  suit: string,
  clientStateVersion?: number,
) {
  return apiFetch<SingleGameResponse<GameFull>>(`/api/games/${gameId}/play`, {
    method: "POST",
    token,
    body: JSON.stringify({ value, suit, clientStateVersion }),
  });
}

export function requestRematch(token: string, gameId: string) {
  return apiFetch<{
    action: "rematch_waiting" | "rematch_started";
    status: "waiting" | "started";
    originalGameId: string;
    game: GameFull | null;
    result: SingleGameResponse<GameFull>["result"] | null;
  }>(`/api/games/${gameId}/rematch`, {
    method: "POST",
    token,
  });
}
