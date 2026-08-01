import { useEffect, useState } from "react";
import { useNavigate } from "react-router";
import { Button } from "../components/Button";
import { motion } from "motion/react";
import {
  BarChart3,
  Flame,
  History,
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
      <header className="app-header">
        <div className="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-black tracking-wide text-white">GARAME</h1>
            <div className="app-brand-underline" />
          </div>
          <div className="flex items-center gap-4">
            <button
              onClick={() => navigate("/wallet")}
              className="flex items-center gap-2 rounded-lg border border-violet-400/25 bg-white/[0.04] px-4 py-2 transition-colors hover:bg-violet-500/10"
            >
              <WalletIcon className="w-5 h-5 text-yellow-300" />
              <span className="text-white font-semibold">{user?.credits ?? 0} credits</span>
            </button>
            <button
              onClick={() => {
                logout();
                navigate("/");
              }}
              className="p-2 hover:bg-white/10 rounded-lg transition-colors"
              title="Deconnexion"
            >
              <LogOut className="w-5 h-5 text-slate-300" />
            </button>
            <div className="w-10 h-10 rounded-full bg-gradient-to-br from-violet-500 via-fuchsia-600 to-[#2a123f] flex items-center justify-center text-white font-bold ring-1 ring-white/15">
              {getInitials(user?.username ?? "Vous")}
            </div>
          </div>
        </div>
      </header>

      <div className="app-container">
        {error && (
          <div className="app-danger-box mb-6">
            {error}
          </div>
        )}

        {isLoading ? (
          <DashboardSkeleton />
        ) : (
          <>
            <section className="mb-8 grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                className="app-card p-6"
              >
                <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                  <div className="flex items-center gap-4">
                    <div className="flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 via-fuchsia-600 to-[#2a123f] text-xl font-black text-white ring-2 ring-violet-300/20">
                      {getInitials(user?.username ?? "Vous")}
                    </div>
                    <div>
                      <div className="text-sm font-semibold uppercase tracking-wide text-violet-300/85">Profil joueur</div>
                      <h2 className="text-3xl font-black text-white">{user?.username ?? "Vous"}</h2>
                      <p className="text-sm text-slate-400">{user?.email}</p>
                    </div>
                  </div>
                  <Button variant="app" size="lg" onClick={() => navigate("/lobby")}>
                    <Play className="w-5 h-5 mr-2 inline" />
                    Jouer maintenant
                  </Button>
                </div>

                <div className="mt-6 grid gap-3 sm:grid-cols-3">
                  <ProfileMetric label="Solde" value={`${summary?.credits ?? user?.credits ?? 0}`} suffix="credits" />
                  <ProfileMetric label="Parties" value={`${totalGames}`} suffix="jouees" />
                  <ProfileMetric label="Winrate" value={`${winRate}%`} suffix={getWinrateLabel(winRate)} />
                </div>
              </motion.div>

              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.05 }}
                className="rounded-2xl border border-violet-300/20 bg-gradient-to-br from-violet-500/16 via-[#151515] to-[#0d0d0e] p-6 shadow-2xl shadow-black/30"
              >
                <div className="flex items-center gap-2 text-violet-200">
                  <Flame className="h-5 w-5" />
                  <span className="font-bold">Dynamique</span>
                </div>
                <div className="mt-4 text-4xl font-black text-white">
                  {streak?.type === "none" ? "Aucune serie" : `${streak?.count ?? 0}`}
                </div>
                <div className="mt-1 text-sm text-slate-300">
                  {streak?.type === "win"
                    ? "victoire(s) de suite"
                    : streak?.type === "loss"
                      ? "defaite(s) de suite"
                      : "Jouez une partie pour lancer une serie"}
                </div>
                <div className="mt-5 rounded-lg border border-white/10 bg-black/35 p-3 text-sm text-neutral-300">
                  Style fort: <span className="font-bold text-yellow-300">{favoriteWinType}</span>
                </div>
              </motion.div>
            </section>

            <div className="grid md:grid-cols-4 gap-4 mb-8">
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
                title="Defaites"
                value={`${losses}`}
                subtitle={totalGames ? `${Math.max(0, 100 - winRate)}% des parties` : "Aucune partie"}
                trend="down"
                tone="rose"
              />
              <StatCard
                icon={<Medal className="w-6 h-6" />}
                title="Specialite"
                value={favoriteWinType}
                subtitle="Type de victoire dominant"
                trend="up"
                tone="amber"
              />
            </div>

            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              className="relative overflow-hidden rounded-2xl border border-violet-300/25 bg-gradient-to-r from-violet-600 via-purple-700 to-[#4b156d] p-8 mb-8 text-center shadow-2xl shadow-black/30"
            >
              <div className="absolute inset-0 bg-black/10" />
              <div className="relative">
              <h2 className="text-3xl font-bold text-white mb-4">Pret a jouer ?</h2>
              <p className="text-violet-50 mb-6">
                Lancez une partie Garame en matchmaking ou rejoignez une table ouverte.
              </p>
              <Button
                variant="appSecondary"
                size="lg"
                onClick={() => navigate("/lobby")}
              >
                <Play className="w-5 h-5 mr-2 inline" />
                Lancer une partie
              </Button>
              </div>
            </motion.div>

            <div className="grid lg:grid-cols-2 gap-6">
              <div className="app-panel p-6">
                <div className="flex items-center gap-2 mb-4">
                  <History className="w-5 h-5 text-violet-300" />
                  <h3 className="text-xl font-bold text-white">Historique recent</h3>
                </div>
                <div className="space-y-3">
                  {recent.length === 0 ? (
                    <EmptyState
                      title="Aucune partie terminee"
                      description="Lancez une partie pour remplir votre historique et suivre vos progres."
                      actionLabel="Jouer maintenant"
                      onAction={() => navigate("/lobby")}
                    />
                  ) : (
                    recent.slice(0, 6).map((item) => (
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
                  <h3 className="text-xl font-bold text-white">Statistiques</h3>
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

function getWinrateLabel(winRate: number) {
  if (winRate >= 70) return "excellent";
  if (winRate >= 50) return "stable";
  if (winRate > 0) return "a travailler";
  return "nouveau";
}

function getFavoriteWinType(byWinType?: ProfileStatsResponse["stats"]["byWinType"]) {
  if (!byWinType) return "A determiner";

  const entries = Object.entries(byWinType).sort(([, a], [, b]) => b.wins - a.wins);
  const [winType, stats] = entries[0] ?? [];
  if (!winType || !stats?.wins) return "A determiner";

  return getWinTypeLabel(winType);
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

function ProfileMetric({ label, value, suffix }: { label: string; value: string; suffix: string }) {
  return (
    <div className="rounded-lg border border-white/10 bg-slate-950/55 p-3">
      <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</div>
      <div className="mt-1 text-2xl font-black text-white">{value}</div>
      <div className="text-xs text-slate-400">{suffix}</div>
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
