import { useEffect, useState } from "react";
import { useNavigate } from "react-router";
import { Button } from "../components/Button";
import { motion } from "motion/react";
import {
  BarChart3,
  Flame,
  History,
  Home,
  LogOut,
  Medal,
  Play,
  Target,
  Trophy,
  TrendingUp,
  Wallet as WalletIcon,
} from "lucide-react";
import { getApiErrorMessage } from "../api/client";
import { getHistory, getProfileStats } from "../api/garameApi";
import type { HistoryResponse, ProfileStatsResponse } from "../api/types";
import { useAuth } from "../auth/AuthContext";
import { getWinTypeLabel } from "../game/cards";

type DashboardData = {
  stats: ProfileStatsResponse["stats"];
  history: HistoryResponse["items"];
};

export function Dashboard() {
  const navigate = useNavigate();
  const { token, user, logout, refreshUser } = useAuth();
  const [data, setData] = useState<DashboardData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    if (!token) return;

    let isMounted = true;
    setIsLoading(true);
    Promise.all([getProfileStats(token), getHistory(token, 1, 10), refreshUser()])
      .then(([statsResponse, historyResponse]) => {
        if (!isMounted) return;
        setData({
          stats: statsResponse.stats,
          history: historyResponse.items,
        });
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

  const summary = data?.stats.summary;
  const recent = data?.stats.recent ?? [];
  const streak = data?.stats.streak;
  const losses = summary?.losses ?? 0;
  const wins = summary?.wins ?? 0;
  const totalGames = summary?.totalGames ?? 0;
  const winRate = summary?.winRate ?? 0;
  const favoriteWinType = getFavoriteWinType(data?.stats.byWinType);

  return (
    <div className="app-page">
      <header className="app-header lg:fixed lg:inset-y-0 lg:left-0 lg:z-40 lg:w-60 lg:border-b-0 lg:border-r">
        <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 lg:h-full lg:flex-col lg:items-stretch lg:px-5 lg:py-8">
          <div>
            <h1 className="app-logo text-2xl lg:text-3xl">GARAME</h1>
            <div className="app-brand-underline" />
          </div>
          <nav className="mt-10 hidden space-y-2 lg:block" aria-label="Navigation principale">
            <button className="flex w-full items-center gap-3 rounded-xl border border-violet-300/25 bg-violet-500/14 px-4 py-3 text-left font-semibold text-white">
              <Home className="h-5 w-5 text-violet-200" /> Accueil
            </button>
            <button onClick={() => navigate("/lobby")} className="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left font-semibold text-neutral-400 transition-colors hover:bg-white/5 hover:text-white">
              <Play className="h-5 w-5" /> Jouer
            </button>
            <button onClick={() => navigate("/wallet")} className="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left font-semibold text-neutral-400 transition-colors hover:bg-white/5 hover:text-white">
              <WalletIcon className="h-5 w-5" /> Portefeuille
            </button>
            <div className="my-5 h-px bg-gradient-to-r from-white/10 to-transparent" />
            <div className="rounded-xl border border-yellow-300/15 bg-yellow-300/5 p-4">
              <div className="text-xs font-bold uppercase tracking-widest text-yellow-200">Votre style</div>
              <div className="mt-2 font-black text-white">{favoriteWinType}</div>
            </div>
          </nav>
          <div className="flex items-center gap-3 lg:mt-auto lg:flex-wrap">
            <button
              onClick={() => navigate("/wallet")}
              className="flex items-center gap-2 rounded-lg border border-violet-400/25 bg-white/[0.04] px-3 py-2 transition-colors hover:bg-violet-500/10 lg:w-full"
            >
              <WalletIcon className="w-5 h-5 text-yellow-300" />
              <span className="text-white font-semibold">{user?.credits ?? 0} crédits</span>
            </button>
            <button
              onClick={() => {
                logout();
                navigate("/");
              }}
              className="p-2 hover:bg-white/10 rounded-lg transition-colors"
              title="Déconnexion"
            >
              <LogOut className="w-5 h-5 text-slate-300" />
            </button>
            <div className="w-10 h-10 rounded-full bg-gradient-to-br from-violet-500 via-fuchsia-600 to-[#2a123f] flex items-center justify-center text-white font-bold ring-1 ring-white/15">
              {getInitials(user?.username ?? "Vous")}
            </div>
          </div>
        </div>
      </header>

      <div className="app-container lg:ml-60 lg:max-w-none lg:px-8 xl:px-12">
        {error && (
          <div className="app-danger-box mb-6">
            {error}
          </div>
        )}

        {isLoading ? (
          <DashboardSkeleton />
        ) : (
          <>
            <section className="mb-8 grid gap-6 lg:grid-cols-[minmax(0,1.55fr)_minmax(280px,0.65fr)]">
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
              className="app-gold-frame relative overflow-hidden rounded-3xl bg-gradient-to-br from-violet-700/38 via-[#18111d] to-[#080809] p-6 sm:p-8"
              >
                <div className="absolute -right-20 -top-28 h-72 w-72 rounded-full bg-violet-500/20 blur-3xl" />
                <div className="absolute -bottom-24 left-1/3 h-48 w-48 rounded-full bg-fuchsia-500/10 blur-3xl" />
                <div className="absolute right-8 top-6 select-none text-8xl text-white/[0.025]">♠</div>
                <div className="absolute bottom-2 right-36 select-none text-7xl text-white/[0.02]">♣</div>
                <div className="relative grid items-center gap-8 xl:grid-cols-[1fr_260px]">
                  <div>
                  <div className="app-eyebrow">Votre prochaine partie</div>
                  <h2 className="mt-3 text-4xl font-black text-white sm:text-5xl">Prêt à jouer, {user?.username ?? "joueur"} ?</h2>
                  <p className="mt-3 max-w-xl text-base text-neutral-300">
                    Trouvez un adversaire de votre niveau et lancez une partie compétitive en quelques secondes.
                  </p>

                  <button
                    type="button"
                    onClick={() => navigate("/lobby")}
                    className="mt-8 flex w-full items-center justify-between rounded-2xl border border-violet-200/45 bg-gradient-to-r from-violet-500 via-purple-600 to-violet-800 px-6 py-5 text-left text-white shadow-[0_20px_55px_rgba(91,33,182,0.45)] transition-all hover:scale-[1.01] hover:brightness-110 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-200 sm:max-w-xl"
                  >
                    <span>
                      <span className="block text-2xl font-black">JOUER</span>
                      <span className="mt-1 block text-sm text-violet-100">Matchmaking rapide · partie d'environ 5 min</span>
                    </span>
                    <span className="flex h-12 w-12 items-center justify-center rounded-full bg-white text-violet-700 shadow-lg">
                      <Play className="h-6 w-6 fill-current" />
                    </span>
                  </button>

                  <div className="mt-6 flex flex-wrap gap-2 text-xs font-semibold text-neutral-300">
                    <span className="rounded-full border border-white/10 bg-black/25 px-3 py-1.5">{totalGames} parties jouées</span>
                    <span className="rounded-full border border-white/10 bg-black/25 px-3 py-1.5">{winRate}% de victoires</span>
                    <span className="rounded-full border border-white/10 bg-black/25 px-3 py-1.5">Solde {summary?.credits ?? user?.credits ?? 0} crédits</span>
                  </div>
                  </div>
                  <div className="relative hidden h-64 xl:block" aria-hidden="true">
                    <div className="absolute left-16 top-8 h-44 w-28 -rotate-12 rounded-2xl border border-violet-300/25 bg-gradient-to-br from-[#271447] to-[#09070d] shadow-2xl" />
                    <div className="absolute right-10 top-8 h-44 w-28 rotate-12 rounded-2xl border border-violet-300/25 bg-gradient-to-br from-[#28154a] to-[#09070d] shadow-2xl" />
                    <div className="absolute left-1/2 top-5 flex h-48 w-32 -translate-x-1/2 items-center justify-center rounded-2xl border border-yellow-300/45 bg-gradient-to-br from-[#3b176e] via-[#180b2d] to-[#08070a] shadow-[0_0_55px_rgba(139,44,245,0.45)]">
                      <span className="text-7xl text-yellow-200 drop-shadow-xl">♠</span>
                    </div>
                    <div className="absolute inset-x-0 bottom-1 text-center text-xs font-bold uppercase tracking-[0.24em] text-violet-200">Partie classée</div>
                  </div>
                </div>
              </motion.div>

              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.05 }}
                className="app-gold-frame rounded-3xl bg-gradient-to-br from-[#18131d] via-[#101011] to-[#080809] p-6"
              >
                <div className="app-eyebrow">Rang joueur</div>
                <div className="mt-5 flex items-center gap-4">
                  <div className="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl border border-yellow-300/40 bg-gradient-to-br from-yellow-300/20 to-yellow-900/10 text-3xl text-yellow-200 shadow-lg shadow-yellow-900/20">♛</div>
                  <div>
                    <div className="text-3xl font-black text-yellow-200">{getRankLabel(winRate)}</div>
                    <div className="mt-1 text-xs text-neutral-400">Progression basée sur votre taux de victoire</div>
                  </div>
                </div>
                <div className="mt-5 h-2 overflow-hidden rounded-full bg-white/8">
                  <div className="h-full rounded-full bg-gradient-to-r from-yellow-500 to-yellow-200" style={{ width: `${Math.max(8, winRate)}%` }} />
                </div>
                <div className="mt-5 flex items-center gap-3 rounded-xl border border-violet-300/15 bg-violet-500/7 p-3">
                  <Flame className="h-5 w-5 text-violet-300" />
                  <div className="text-sm text-slate-300">
                  {streak?.type === "win"
                    ? `${streak.count} victoire(s) de suite`
                    : streak?.type === "loss"
                      ? `${streak.count} défaite(s) de suite`
                      : "Jouez pour lancer une série"}
                  </div>
                </div>
                <div className="mt-3 rounded-lg border border-violet-300/20 bg-violet-500/8 p-3">
                  <div className="flex items-center gap-2 text-sm font-bold text-white">
                    <Target className="h-4 w-4 text-violet-300" /> Objectif du jour
                  </div>
                  <p className="mt-1 text-xs text-neutral-400">Jouez une partie pour maintenir votre progression.</p>
                </div>
              </motion.div>
            </section>

            <div className="grid grid-cols-2 gap-4 mb-8 lg:grid-cols-4">
              <StatCard
                icon={<Trophy className="w-6 h-6" />}
                title="Victoires"
                value={`${wins}`}
                subtitle={`${totalGames} parties jouees`}
                trend="up"
                tone="violet"
              />
              <StatCard
                icon={<TrendingUp className="w-6 h-6" />}
                title="Winrate"
                value={`${winRate}%`}
                subtitle={streak?.type === "none" ? "Aucune serie" : `${streak?.count ?? 0} ${streak?.type === "win" ? "victoire(s)" : "defaite(s)"} de suite`}
                trend={streak?.type === "loss" ? "down" : "up"}
                tone="yellow"
              />
              <StatCard
                icon={<Target className="w-6 h-6" />}
                title="Défaites"
                value={`${losses}`}
                subtitle={totalGames ? `${Math.max(0, 100 - winRate)}% des parties` : "Aucune partie"}
                trend="down"
                tone="rose"
              />
              <StatCard
                icon={<Medal className="w-6 h-6" />}
                title="Specialite"
                value={favoriteWinType}
                subtitle="Style de victoire dominant"
                trend="up"
                tone="amber"
              />
            </div>

            <div className="grid lg:grid-cols-2 gap-6">
              <div className="app-panel p-6">
                <div className="flex items-center gap-2 mb-4">
                  <History className="w-5 h-5 text-violet-300" />
                  <h3 className="text-xl font-bold text-white">Historique récent</h3>
                </div>
                <div className="space-y-3">
                  {recent.length === 0 ? (
                    <EmptyState
                      title="Aucune partie terminée"
                      description="Lancez une partie pour remplir votre historique et suivre vos progrès."
                      actionLabel="Jouer maintenant"
                      onAction={() => navigate("/lobby")}
                    />
                  ) : (
                    recent.slice(0, 4).map((item) => (
                      <GameHistoryItem
                        key={item.resultId}
                        opponent={item.opponent.username}
                        result={item.didWin ? "win" : "loss"}
                        amount={item.creditsDelta}
                        time={formatDate(item.playedAt)}
                        winType={item.winType}
                      />
                    ))
                  )}
                </div>
              </div>

              <div className="app-panel p-6">
                <div className="flex items-center gap-2 mb-4">
                  <BarChart3 className="w-5 h-5 text-yellow-300" />
                  <h3 className="text-xl font-bold text-white">Repères de jeu</h3>
                </div>
                <div className="space-y-4">
                  <StatRow label="Parties jouees" value={`${summary?.gamesPlayed ?? 0}`} />
                  <StatRow label="Parties gagnees" value={`${summary?.gamesWon ?? 0}`} />
                  <StatRow label="Victoires Korat" value={`${data?.stats.byWinType.korat.wins ?? 0}`} />
                  <StatRow label="Three 7 reussis" value={`${data?.stats.byWinType.three_seven.wins ?? 0}`} />
                  <StatRow label="Moins de 21 reussis" value={`${data?.stats.byWinType.moins_21.wins ?? 0}`} />
                  <StatRow label="Defaites" value={`${summary?.losses ?? 0}`} />
                </div>
              </div>
            </div>
          </>
        )}
      </div>
    </div>
  );
}

function getInitials(username: string) {
  return username.slice(0, 2).toUpperCase();
}

function formatDate(date: string) {
  return new Intl.DateTimeFormat("fr-FR", {
    day: "2-digit",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(date));
}

function getFavoriteWinType(byWinType?: ProfileStatsResponse["stats"]["byWinType"]) {
  if (!byWinType) return "A determiner";

  const entries = Object.entries(byWinType).sort(([, a], [, b]) => b.wins - a.wins);
  const [winType, stats] = entries[0] ?? [];
  if (!winType || !stats?.wins) return "A determiner";

  return getWinTypeLabel(winType);
}

function getRankLabel(winRate: number) {
  if (winRate >= 70) return "Or I";
  if (winRate >= 55) return "Or II";
  if (winRate >= 40) return "Argent I";
  if (winRate >= 25) return "Argent II";
  return "Bronze I";
}

function DashboardSkeleton() {
  return (
    <div className="space-y-8">
      <div className="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
        <div className="h-48 animate-pulse rounded-2xl bg-slate-900" />
        <div className="h-48 animate-pulse rounded-2xl bg-slate-900" />
      </div>
      <div className="grid gap-4 md:grid-cols-4">
        {Array.from({ length: 4 }).map((_, index) => (
          <div key={index} className="h-32 animate-pulse rounded-xl bg-slate-900" />
        ))}
      </div>
      <div className="h-52 animate-pulse rounded-2xl bg-slate-900" />
    </div>
  );
}

function StatCard({
  icon,
  title,
  value,
  subtitle,
  trend,
  tone,
}: {
  icon: React.ReactNode;
  title: string;
  value: string;
  subtitle: string;
  trend: "up" | "down";
  tone: "violet" | "yellow" | "rose" | "amber";
}) {
  const toneClass = {
    violet: {
      card: "border-violet-300/18 bg-violet-500/9",
      icon: "bg-violet-500/14 text-violet-200",
      value: "text-violet-50",
    },
    yellow: {
      card: "border-yellow-300/18 bg-yellow-400/8",
      icon: "bg-yellow-400/12 text-yellow-300",
      value: "text-yellow-50",
    },
    rose: {
      card: "border-rose-300/18 bg-rose-500/8",
      icon: "bg-rose-400/12 text-rose-300",
      value: "text-rose-50",
    },
    amber: {
      card: "border-orange-300/18 bg-orange-400/8",
      icon: "bg-orange-400/12 text-orange-300",
      value: "text-orange-50",
    },
  }[tone];

  return (
    <motion.div
      whileHover={{ y: -4 }}
      className={`rounded-xl border bg-slate-900/70 p-6 backdrop-blur ${toneClass.card}`}
    >
      <div className="flex items-center gap-3 mb-3">
        <div className={`p-2 rounded-lg ${toneClass.icon}`}>{icon}</div>
        <span className="text-slate-400">{title}</span>
      </div>
      <div className={`text-3xl font-bold mb-1 ${toneClass.value}`}>{value}</div>
      <div className={`text-sm ${trend === "up" ? "text-yellow-300" : "text-rose-300"}`}>
        {subtitle}
      </div>
    </motion.div>
  );
}

function GameHistoryItem({
  opponent,
  result,
  amount,
  time,
  winType,
}: {
  opponent: string;
  result: "win" | "loss";
  amount: number;
  time: string;
  winType: string;
}) {
  return (
    <div className="flex items-center justify-between rounded-lg border border-white/5 bg-slate-950/45 p-3">
      <div>
        <div className="text-white font-medium">
          vs {opponent}
          <span className="ml-2 rounded bg-violet-400/15 px-2 py-0.5 text-xs font-bold text-violet-200">
            {getWinTypeLabel(winType)}
          </span>
        </div>
        <div className="text-sm text-slate-500">{time}</div>
      </div>
      <div className={`font-bold ${result === "win" ? "text-green-400" : "text-red-400"}`}>
        {amount > 0 ? "+" : ""}
        {amount} credits
      </div>
    </div>
  );
}

function StatRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between rounded-lg border border-white/5 bg-slate-950/35 px-3 py-2">
      <span className="text-slate-400">{label}</span>
      <span className="text-white font-semibold">{value}</span>
    </div>
  );
}

function EmptyState({
  title,
  description,
  actionLabel,
  onAction,
}: {
  title: string;
  description: string;
  actionLabel: string;
  onAction: () => void;
}) {
  return (
    <div className="rounded-xl border border-dashed border-violet-300/20 bg-violet-500/5 p-6 text-center">
      <div className="font-semibold text-white">{title}</div>
      <div className="mt-1 text-sm text-slate-400">{description}</div>
      <Button variant="appOutline" className="mt-4" size="sm" onClick={onAction}>
        {actionLabel}
      </Button>
    </div>
  );
}
