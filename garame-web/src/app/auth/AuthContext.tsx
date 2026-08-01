import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import { getMe, login as loginRequest, register as registerRequest } from "../api/garameApi";
import type { ApiUser } from "../api/types";

const STORAGE_KEY = "garame.auth";

interface StoredAuth {
  token: string;
  user: ApiUser;
}

interface AuthContextValue {
  token: string | null;
  user: ApiUser | null;
  isAuthenticated: boolean;
  isBootstrapping: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (username: string, email: string, password: string) => Promise<void>;
  refreshUser: () => Promise<void>;
  logout: () => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

function readStoredAuth(): StoredAuth | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? (JSON.parse(raw) as StoredAuth) : null;
  } catch {
    localStorage.removeItem(STORAGE_KEY);
    return null;
  }
}

function writeStoredAuth(auth: StoredAuth | null) {
  if (!auth) {
    localStorage.removeItem(STORAGE_KEY);
    return;
  }

  localStorage.setItem(STORAGE_KEY, JSON.stringify(auth));
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<ApiUser | null>(null);
  const [isBootstrapping, setIsBootstrapping] = useState(true);

  const logout = useCallback(() => {
    setToken(null);
    setUser(null);
    writeStoredAuth(null);
  }, []);

  const setAuth = useCallback((auth: StoredAuth) => {
    setToken(auth.token);
    setUser(auth.user);
    writeStoredAuth(auth);
  }, []);

  useEffect(() => {
    const stored = readStoredAuth();
    if (!stored) {
      setIsBootstrapping(false);
      return;
    }

    setToken(stored.token);
    setUser(stored.user);
    getMe(stored.token)
      .then(({ user: freshUser }) => setAuth({ token: stored.token, user: freshUser }))
      .catch(logout)
      .finally(() => setIsBootstrapping(false));
  }, [logout, setAuth]);

  const login = useCallback(
    async (email: string, password: string) => {
      const auth = await loginRequest(email, password);
      setAuth(auth);
    },
    [setAuth],
  );

  const register = useCallback(
    async (username: string, email: string, password: string) => {
      const auth = await registerRequest(username, email, password);
      setAuth(auth);
    },
    [setAuth],
  );

  const refreshUser = useCallback(async () => {
    if (!token) return;
    const { user: freshUser } = await getMe(token);
    setAuth({ token, user: freshUser });
  }, [setAuth, token]);

  const value = useMemo<AuthContextValue>(
    () => ({
      token,
      user,
      isAuthenticated: Boolean(token && user),
      isBootstrapping,
      login,
      register,
      refreshUser,
      logout,
    }),
    [isBootstrapping, login, logout, refreshUser, register, token, user],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used inside AuthProvider");
  }
  return context;
}
