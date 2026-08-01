import { useEffect, useState } from "react";
import { useNavigate } from "react-router";
import { Button } from "../components/Button";
import { motion } from "motion/react";
import {
  ArrowDownLeft,
  ArrowLeft,
  ArrowUpRight,
  Clock,
  Info,
  Scale,
  ShieldCheck,
  UserCheck,
  WalletCards,
} from "lucide-react";
import { getApiErrorMessage } from "../api/client";
import { getProfileStats } from "../api/garameApi";
import type { ProfileStatsResponse } from "../api/types";
import { useAuth } from "../auth/AuthContext";
import { getWinTypeLabel } from "../game/cards";

export function Wallet() {
  const navigate = useNavigate();
  const { token, user, refreshUser } = useAuth();
  const [stats, setStats] = useState<ProfileStatsResponse["stats"] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    if (!token) return;

    let isMounted = true;
    setIsLoading(true);
    Promise.all([getProfileStats(token), refreshUser()])
      .then(([response]) => {
        if (!isMounted) return;
        setStats(response.stats);
        setError(null);
      })
      .catch((loadError) => {
        if (isMounted) setError(getApiErrorMessage(loadError));
      })
      .finally(() => {
        if (isMounted) setIsLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [refreshUser, token]);

  const summary = stats?.summary;
  const credits = summary?.credits ?? user?.credits ?? 0;

  return (
    <div className="app-page">
      <header className="app-header">
        <div className="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
          <div className="flex items-center gap-4">
            <button
              onClick={() => navigate("/dashboard")}
              className="p-2 hover:bg-white/10 rounded-lg transition-colors"
            >
              <ArrowLeft className="w-5 h-5 text-white" />
            </button>
            <h1 className="text-2xl font-bold text-white">Credits</h1>
          </div>
        </div>
      </header>

      <div className="max-w-4xl mx-auto px-4 py-8">
        {error && (
          <div className="app-danger-box mb-6">
            {error}
          </div>
        )}

        {isLoading ? (
          <WalletSkeleton />
        ) : (
          <>
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              className="bg-gradient-to-r from-violet-600 via-purple-700 to-[#4b156d] rounded-2xl p-8 mb-8 shadow-2xl shadow-black/30 border border-violet-300/20"
            >
              <div className="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <div className="text-violet-100 mb-2">Solde disponible</div>
                  <div className="text-5xl font-bold text-white mb-2">{credits}</div>
                  <div className="text-sm text-violet-50">credits de jeu</div>
                </div>
                <div className="rounded-xl border border-white/20 bg-white/10 p-4 text-sm text-white backdrop-blur">
                  <div className="flex items-center gap-2 font-bold">
                    <ShieldCheck className="h-4 w-4" />
                    Compte credits
                  </div>
                  <div className="mt-1 text-violet-50">Depot/retrait reel non connecte au backend.</div>
                </div>
              </div>

              <div className="mt-6 grid grid-cols-2 gap-4">
                <div className="bg-white/10 backdrop-blur rounded-lg p-4">
                  <div className="text-violet-100 text-sm mb-1">Parties jouees</div>
                  <div className="text-2xl font-bold text-white">{summary?.totalGames ?? 0}</div>
                </div>
                <div className="bg-white/10 backdrop-blur rounded-lg p-4">
                  <div className="text-violet-100 text-sm mb-1">Winrate</div>
                  <div className="text-2xl font-bold text-white">{summary?.winRate ?? 0}%</div>
                </div>
              </div>
            </motion.div>

            <div className="mb-8 grid gap-4 md:grid-cols-3">
              <WalletActionCard
                icon={<ArrowDownLeft className="h-5 w-5" />}
                title="Depot"
                description="Preparer l'alimentation du compte joueur."
                status="Bientot"
              />
              <WalletActionCard
                icon={<ArrowUpRight className="h-5 w-5" />}
                title="Retrait"
                description="Afficher le statut des demandes de retrait."
                status="Bientot"
              />
              <WalletActionCard
                icon={<Clock className="h-5 w-5" />}
                title="Limites"
                description="Limiter depot, perte et duree de session."
                status="A definir"
              />
            </div>

            <div className="mb-8 grid gap-4 md:grid-cols-2">
              <TrustCard
                icon={<UserCheck className="h-5 w-5" />}
                title="Verification d'identite"
                description="Avant toute operation en argent reel, le compte devra verifier age, identite et pays de residence."
                status="KYC requis"
              />
              <TrustCard
                icon={<Scale className="h-5 w-5" />}
                title="Jeu responsable"
                description="Les limites de depot, perte et session devront etre visibles et modifiables avant le lancement argent reel."
                status="A integrer"
              />
            </div>
          </>
        )}

        <div className="app-panel mb-8 flex gap-3 p-6">
          <Info className="w-5 h-5 text-yellow-300 flex-shrink-0 mt-0.5" />
          <div className="text-sm text-slate-300">
            L'API actuelle gere un solde de credits de jeu. Les depots, retraits et transactions
            monetaires ne sont pas encore exposes par le backend.
          </div>
        </div>

        <div className="app-panel p-6">
          <div className="mb-4 flex items-center justify-between gap-3">
            <div>
              <h3 className="text-xl font-bold text-white">Derniers mouvements</h3>
              <p className="text-sm text-slate-400">Historique des gains et pertes de credits.</p>
            </div>
            <WalletCards className="h-5 w-5 text-violet-300" />
          </div>
          {stats?.recent.length ? (
            <div className="space-y-3">
              {stats.recent.map((item) => (
                <Transaction
                  key={item.resultId}
                  type={item.didWin ? "win" : "loss"}
                  description={`${getWinTypeLabel(item.winType)} vs ${item.opponent.username}`}
                  amount={item.creditsDelta}
                  date={formatDate(item.playedAt)}
                />
              ))}
            </div>
          ) : (
            <div className="rounded-xl border border-dashed border-violet-300/20 bg-violet-500/5 p-6 text-center">
              <div className="font-semibold text-white">Aucun mouvement</div>
              <div className="mt-1 text-sm text-slate-400">
                Jouez une partie pour afficher vos gains et pertes ici.
              </div>
            </div>
          )}
        </div>

        <div className="mt-6">
          <Button variant="app" className="w-full" onClick={() => navigate("/lobby")}>
            Jouer pour gagner des credits
          </Button>
        </div>
      </div>
    </div>
  );
}

function formatDate(date: string) {
  return new Intl.DateTimeFormat("fr-FR", {
    day: "2-digit",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(date));
}

function WalletSkeleton() {
  return (
    <div className="mb-8 space-y-4">
      <div className="h-64 animate-pulse rounded-2xl bg-slate-900" />
      <div className="grid gap-4 md:grid-cols-3">
        {Array.from({ length: 3 }).map((_, index) => (
          <div key={index} className="h-32 animate-pulse rounded-xl bg-slate-900" />
        ))}
      </div>
    </div>
  );
}

function WalletActionCard({
  icon,
  title,
  description,
  status,
}: {
  icon: React.ReactNode;
  title: string;
  description: string;
  status: string;
}) {
  return (
    <div className="rounded-xl border border-white/10 bg-[#151515]/88 p-5">
      <div className="mb-4 flex items-center justify-between gap-3">
        <div className="rounded-lg bg-violet-500/10 p-2 text-violet-200">{icon}</div>
        <span className="rounded-full border border-white/10 bg-black/30 px-2 py-1 text-xs font-semibold text-slate-400">
          {status}
        </span>
      </div>
      <div className="font-bold text-white">{title}</div>
      <div className="mt-1 text-sm text-slate-400">{description}</div>
    </div>
  );
}

function TrustCard({
  icon,
  title,
  description,
  status,
}: {
  icon: React.ReactNode;
  title: string;
  description: string;
  status: string;
}) {
  return (
    <div className="rounded-xl border border-violet-400/25 bg-violet-500/10 p-5">
      <div className="mb-3 flex items-center justify-between gap-3">
        <div className="flex items-center gap-2 font-bold text-violet-100">
          <span className="rounded-lg bg-violet-300/10 p-2 text-violet-200">{icon}</span>
          {title}
        </div>
        <span className="rounded-full border border-yellow-300/30 bg-black/30 px-2 py-1 text-xs font-semibold text-yellow-100">
          {status}
        </span>
      </div>
      <div className="text-sm leading-relaxed text-violet-50/80">{description}</div>
    </div>
  );
}

function Transaction({
  type,
  description,
  amount,
  date,
}: {
  type: "win" | "loss";
  description: string;
  amount: number;
  date: string;
}) {
  const isWin = type === "win";

  return (
    <div className="flex items-center justify-between p-3 bg-black/25 border border-white/5 rounded-lg">
      <div className="flex items-center gap-3">
        {isWin ? (
          <ArrowDownLeft className="w-5 h-5 text-yellow-300" />
        ) : (
          <ArrowUpRight className="w-5 h-5 text-red-400" />
        )}
        <div>
          <div className="text-white font-medium">{description}</div>
          <div className="text-sm text-slate-500">{date}</div>
        </div>
      </div>
      <div className={`font-bold ${isWin ? "text-yellow-300" : "text-rose-300"}`}>
        {amount > 0 ? "+" : ""}
        {amount} credits
      </div>
    </div>
  );
}
