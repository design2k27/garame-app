import { useEffect, useState } from "react";
import { useLocation, useNavigate } from "react-router";
import { Button } from "../components/Button";
import { motion } from "motion/react";
import { Mail, Lock, User } from "lucide-react";
import { getApiErrorMessage } from "../api/client";
import { useAuth } from "../auth/AuthContext";

export function AuthPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const { login, register, isAuthenticated } = useAuth();
  const [isLogin, setIsLogin] = useState(true);
  const [username, setUsername] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const redirectTo = (location.state as { from?: string } | null)?.from ?? "/dashboard";

  useEffect(() => {
    if (isAuthenticated) {
      navigate(redirectTo, { replace: true });
    }
  }, [isAuthenticated, navigate, redirectTo]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (!isLogin && password !== confirmPassword) {
      setError("Les mots de passe ne correspondent pas.");
      return;
    }

    setIsSubmitting(true);
    try {
      if (isLogin) {
        await login(email, password);
      } else {
        await register(username, email, password);
      }
      navigate(redirectTo, { replace: true });
    } catch (submitError) {
      setError(getApiErrorMessage(submitError));
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="app-page flex items-center justify-center px-4">
      <motion.div
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        className="w-full max-w-md"
      >
        <div className="app-card p-8">
          <div className="text-center mb-8">
            <h1 className="app-brand-text text-4xl font-bold mb-2">
              GARAME
            </h1>
            <p className="text-slate-400">
              {isLogin ? "Connexion à votre compte" : "Créer un compte"}
            </p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            {!isLogin && (
              <div>
                <label className="block text-sm text-slate-300 mb-2">Pseudo</label>
                <div className="relative">
                  <User className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-500" />
                  <input
                    type="text"
                    placeholder="Votre pseudo"
                    value={username}
                    onChange={(event) => setUsername(event.target.value)}
                    required={!isLogin}
                    className="app-input"
                  />
                </div>
              </div>
            )}

            <div>
              <label className="block text-sm text-slate-300 mb-2">Email</label>
              <div className="relative">
                <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-500" />
                <input
                  type="email"
                  placeholder="votre@email.com"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  required
                  className="app-input"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm text-slate-300 mb-2">Mot de passe</label>
              <div className="relative">
                <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-500" />
                <input
                  type="password"
                  placeholder="••••••••"
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  required
                  className="app-input"
                />
              </div>
            </div>

            {!isLogin && (
              <div>
                <label className="block text-sm text-slate-300 mb-2">
                  Confirmer le mot de passe
                </label>
                <div className="relative">
                  <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-500" />
                  <input
                    type="password"
                    placeholder="••••••••"
                    value={confirmPassword}
                    onChange={(event) => setConfirmPassword(event.target.value)}
                    required={!isLogin}
                    className="app-input"
                  />
                </div>
              </div>
            )}

            {error && (
              <div className="app-danger-box">
                {error}
              </div>
            )}

            <Button variant="app" type="submit" className="w-full mt-6" disabled={isSubmitting}>
              {isSubmitting
                ? "Veuillez patienter..."
                : isLogin
                  ? "Se connecter"
                  : "Créer un compte"}
            </Button>
          </form>

          <div className="mt-6 text-center">
            <button
              onClick={() => {
                setIsLogin(!isLogin);
                setError(null);
              }}
              className="app-action-text"
            >
              {isLogin
                ? "Pas encore de compte ? S'inscrire"
                : "Déjà un compte ? Se connecter"}
            </button>
          </div>
        </div>
      </motion.div>
    </div>
  );
}
