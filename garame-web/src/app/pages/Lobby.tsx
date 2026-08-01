import { type ReactNode, useEffect, useState } from "react";
import { useNavigate } from "react-router";
import { Button } from "../components/Button";
import { motion } from "motion/react";
import {
  ArrowLeft,
  Clock,
  Coins,
  Lock,
  RefreshCw,
  ShieldCheck,
  Trophy,
  Users,
  Zap,
} from "lucide-react";
import { ApiError, getApiErrorMessage } from "../api/client";
import {
  cancelMatchmaking,
  createGame,
  getMyActiveGames,
  getOpenGames,
  joinGame,
  pollGame,
  startMatchmaking,
} from "../api/garameApi";
import type { GameSummary } from "../api/types";
import { useAuth } from "../auth/AuthContext";

export function Lobby() {
  const navigate = useNavigate();
  const { token, user } = useAuth();
  const [selectedStake, setSelectedStake] = useState(10);
  const [isSearching, setIsSearching] = useState(false);
  const [queuedGame, setQueuedGame] = useState<GameSummary | null>(null);
  const [openGames, setOpenGames] = useState<GameSummary[]>([]);
  const [onlineCount, setOnlineCount] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isCreating, setIsCreating] = useState(false);
  const [needsStakeConfirmation, setNeedsStakeConfirmation] = useState(false);

  const stakes = [10, 20, 30, 40];
  const estimatedFee = Math.max(1, Math.round(selectedStake * 0.1));
  const estimatedGain = selectedStake * 2 - estimatedFee;

  const refreshLobby = async () => {
    if (!token) return;
    const [openResponse, activeResponse] = await Promise.all([
      getOpenGames(token),
      getMyActiveGames(token),
    ]);

    const activeGame = activeResponse.games[0];
    if (activeGame?.status === "playing") {
      navigate(`/game/${activeGame.id}`);
      return;
    }

    if (activeGame?.status === "waiting") {
      setQueuedGame(activeGame);
      setIsSearching(true);
    }

    setOpenGames(openResponse.games);
    setOnlineCount(countUniquePlayers(openResponse.games) + activeResponse.games.length);
  };

  useEffect(() => {
    if (!token) return;

    let isMounted = true;
    setIsLoading(true);
    refreshLobby()
      .catch((loadError) => {
        if (isMounted) setError(getApiErrorMessage(loadError));
      })
      .finally(() => {
        if (isMounted) setIsLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [navigate, token]);

  useEffect(() => {
    if (!token || !queuedGame || !isSearching) return;

    let timeoutId: number | undefined;
    let cancelled = false;

    const tick = async () => {
      try {
        const response = await pollGame(token, queuedGame.id, queuedGame.stateVersion);
        if (cancelled) return;

        if (response.changed && response.game && response.game.status !== "waiting") {
          navigate(`/game/${response.game.id}`);
          return;
        }

        timeoutId = window.setTimeout(tick, response.pollAfterMs ?? 1500);
      } catch (pollError) {
        if (!cancelled) {
          setError(getApiErrorMessage(pollError));
          timeoutId = window.setTimeout(tick, 2500);
        }
      }
    };

    timeoutId = window.setTimeout(tick, 1000);

    return () => {
      cancelled = true;
      if (timeoutId) window.clearTimeout(timeoutId);
    };
  }, [isSearching, navigate, queuedGame, token]);

  const handleQuickMatch = async () => {
    if (!token) return;

    if (selectedStake >= 40 && !needsStakeConfirmation) {
      setNeedsStakeConfirmation(true);
      return;
    }

    setError(null);
    setIsSearching(true);
    setNeedsStakeConfirmation(false);
    try {
      const response = await startMatchmaking(token);
      if (response.action === "joined" || response.game.status !== "waiting") {
        navigate(`/game/${response.game.id}`);
        return;
      }

      setQueuedGame(response.game);
    } catch (matchmakingError) {
      if (matchmakingError instanceof ApiError && matchmakingError.code === "active_game_exists") {
        await refreshLobby();
        return;
      }

      setIsSearching(false);
      setError(getApiErrorMessage(matchmakingError));
    }
  };

  const handleCancelSearch = async () => {
    if (!token) return;
    try {
      await cancelMatchmaking(token);
      setIsSearching(false);
      setQueuedGame(null);
      await refreshLobby();
    } catch (cancelError) {
      setError(getApiErrorMessage(cancelError));
    }
  };

  const handleCreateGame = async () => {
    if (!token) return;

    setIsCreating(true);
    setError(null);
    try {
      const response = await createGame(token);
      setQueuedGame(response.game);
      setIsSearching(true);
    } catch (createError) {
      setError(getApiErrorMessage(createError));
    } finally {
      setIsCreating(false);
    }
  };

  const handleJoin = async (gameId: string) => {
    if (!token) return;

    setError(null);
    try {
      const response = await joinGame(token, gameId);
      navigate(`/game/${response.game.id}`);
    } catch (joinError) {
      setError(getApiErrorMessage(joinError));
      await refreshLobby();
    }
  };

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
            <div>
              <h1 className="text-2xl font-bold text-white">Lobby</h1>
              <p className="text-sm text-slate-400">Tables, matchmaking et mises indicatives</p>
            </div>
          </div>
          <div className="flex items-center gap-2 rounded-lg border border-violet-400/25 bg-white/[0.04] px-4 py-2">
            <Users className="w-5 h-5 text-violet-300" />
            <span className="text-white font-semibold">{onlineCount} actif(s)</span>
          </div>
        </div>
      </header>

      <div className="mx-auto max-w-6xl px-4 py-8">
        {error && (
          <div className="app-danger-box mb-6">
            {error}
          </div>
        )}

        {isLoading ? (
          <LobbySkeleton />
        ) : !isSearching ? (
          <>
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              className="app-card mb-8 overflow-hidden"
            >
              <div className="grid gap-0 lg:grid-cols-[1.15fr_0.85fr]">
                <div className="p-6 sm:p-8">
                  <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-violet-400/35 bg-violet-500/10 px-4 py-2 text-violet-200">
                    <Zap className="w-4 h-4" />
                    <span className="font-semibold">Match rapide</span>
                  </div>
                  <h2 className="mb-2 text-3xl font-black text-white">Jouer maintenant</h2>
                  <p className="max-w-2xl text-slate-400">
                    Choisissez une mise indicative, verifiez le gain potentiel, puis lancez la recherche d'un adversaire.
                  </p>

                  <div className="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {stakes.map((stake) => {
                      const fee = Math.max(1, Math.round(stake * 0.1));
                      const gain = stake * 2 - fee;

                      return (
                        <motion.button
                          key={stake}
                          whileHover={{ scale: 1.02 }}
                          whileTap={{ scale: 0.98 }}
                          onClick={() => {
                            setSelectedStake(stake);
                            setNeedsStakeConfirmation(false);
                          }}
                          className={`rounded-xl border p-4 text-left transition-all ${
                            selectedStake === stake
                              ? "border-violet-400 bg-violet-500/15 shadow-lg shadow-violet-950/30"
                              : "border-white/10 bg-black/25 hover:border-violet-300/40"
                          }`}
                        >
                          <div className="flex items-center justify-between gap-2">
                            <div className="text-2xl font-black text-white">{stake}</div>
                            {selectedStake === stake && (
                              <div className="h-2.5 w-2.5 rounded-full bg-yellow-300 shadow-lg shadow-yellow-300/60" />
                            )}
                          </div>
                          <div className="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            credits
                          </div>
                          <div className="mt-3 rounded-md border border-white/10 bg-black/30 px-2 py-1 text-xs text-yellow-200">
                            Gain estim. {gain}
                          </div>
                        </motion.button>
                      );
                    })}
                  </div>

                  {needsStakeConfirmation && (
                    <div className="mt-5 rounded-xl border border-yellow-300/35 bg-yellow-300/10 p-4">
                      <div className="font-bold text-yellow-100">Confirmer cette mise</div>
                      <div className="mt-1 text-sm text-yellow-50/80">
                        Cette table utilise la mise la plus haute disponible dans l'interface actuelle.
                        Verifiez votre solde et votre limite avant de continuer.
                      </div>
                    </div>
                  )}

                  <Button variant="app" size="lg" className="mt-7 w-full" onClick={handleQuickMatch}>
                    <Zap className="w-5 h-5 mr-2 inline" />
                    {needsStakeConfirmation ? "Confirmer et jouer" : "Jouer maintenant"}
                  </Button>
                </div>

                <div className="border-t border-white/10 bg-black/25 p-6 sm:p-8 lg:border-l lg:border-t-0">
                  <div className="mb-4 text-sm font-bold uppercase tracking-wide text-slate-400">
                    Resume de la table
                  </div>
                  <div className="space-y-3">
                    <EconomyRow icon={<Coins className="h-4 w-4" />} label="Votre solde" value={`${user?.credits ?? 0} credits`} />
                    <EconomyRow icon={<Lock className="h-4 w-4" />} label="Mise" value={`${selectedStake} credits`} />
                    <EconomyRow icon={<ShieldCheck className="h-4 w-4" />} label="Frais estimes" value={`${estimatedFee} credits`} />
                    <EconomyRow icon={<Trophy className="h-4 w-4" />} label="Gain potentiel" value={`${estimatedGain} credits`} accent />
                  </div>
                  <div className="mt-5 rounded-lg border border-white/10 bg-[#151515] p-3 text-xs leading-relaxed text-neutral-400">
                    Les montants sont indicatifs tant que le backend wallet n'a pas de contrat de mise reel dedie.
                  </div>
                  <div className="mt-3 rounded-lg border border-violet-400/30 bg-violet-500/10 p-3 text-xs leading-relaxed text-violet-100">
                    Jeu responsable: ne misez que des credits que vous acceptez de perdre. Les limites de jeu
                    devront etre appliquees avant toute mise en argent reel.
                  </div>
                </div>
              </div>
            </motion.div>

            <div className="app-card p-8">
              <div className="flex flex-col gap-4 mb-6 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h3 className="text-2xl font-bold text-white mb-1">Tables ouvertes</h3>
                  <p className="text-slate-400">
                    {openGames.length} table{openGames.length > 1 ? "s" : ""} disponible{openGames.length > 1 ? "s" : ""}
                  </p>
                </div>
                <div className="flex gap-2">
                  <Button variant="appOutline" onClick={() => refreshLobby()}>
                    <RefreshCw className="w-4 h-4 mr-2 inline" />
                    Actualiser
                  </Button>
                  <Button variant="appOutline" onClick={handleCreateGame} disabled={isCreating}>
                    <Lock className="w-4 h-4 mr-2 inline" />
                    {isCreating ? "Creation..." : "Creer"}
                  </Button>
                </div>
              </div>

              <div className="space-y-3">
                {openGames.length === 0 ? (
                  <div className="rounded-xl border border-dashed border-violet-300/20 bg-violet-500/5 p-8 text-center">
                    <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full border border-violet-300/20 bg-black/30 text-violet-200">
                      <Users className="h-5 w-5" />
                    </div>
                    <div className="font-semibold text-white">Aucune table ouverte</div>
                    <div className="mt-1 text-sm text-slate-400">
                      Lancez un matchmaking ou creez une table pour attendre un adversaire.
                    </div>
                  </div>
                ) : (
                  openGames.map((game) => (
                    <PrivateGameItem
                      key={game.id}
                      game={game}
                      onJoin={() => handleJoin(game.id)}
                    />
                  ))
                )}
              </div>
            </div>
          </>
        ) : (
          <MatchmakingScreen
            stake={selectedStake}
            estimatedGain={estimatedGain}
            queuedGame={queuedGame}
            onCancel={handleCancelSearch}
          />
        )}
      </div>
    </div>
  );
}

function countUniquePlayers(games: GameSummary[]) {
  return new Set(games.flatMap((game) => game.players.map((player) => player.id))).size;
}

function LobbySkeleton() {
  return (
    <div className="space-y-8">
      <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-8">
        <div className="h-8 w-48 animate-pulse rounded-lg bg-slate-800" />
        <div className="mt-4 h-4 w-full max-w-lg animate-pulse rounded bg-slate-800" />
        <div className="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
          {Array.from({ length: 4 }).map((_, index) => (
            <div key={index} className="h-28 animate-pulse rounded-xl bg-slate-800" />
          ))}
        </div>
        <div className="mt-8 h-14 animate-pulse rounded-lg bg-slate-800" />
      </div>
      <div className="h-52 animate-pulse rounded-2xl border border-slate-800 bg-slate-900/60" />
    </div>
  );
}

function EconomyRow({
  icon,
  label,
  value,
  accent = false,
}: {
  icon: ReactNode;
  label: string;
  value: string;
  accent?: boolean;
}) {
  return (
    <div className="flex items-center justify-between gap-3 rounded-lg border border-white/10 bg-[#101010] px-3 py-3">
      <div className="flex min-w-0 items-center gap-2 text-slate-400">
        <span className={accent ? "text-yellow-300" : "text-violet-200"}>{icon}</span>
        <span className="truncate text-sm">{label}</span>
      </div>
      <div className={`shrink-0 text-sm font-black ${accent ? "text-yellow-300" : "text-white"}`}>
        {value}
      </div>
    </div>
  );
}

function PrivateGameItem({
  game,
  onJoin,
}: {
  game: GameSummary;
  onJoin: () => void;
}) {
  const host = game.players[0]?.username ?? "Joueur";
  const currentPlayers = game.players.length;

  return (
    <div className="flex flex-col gap-4 rounded-xl border border-white/10 bg-[#101010] p-4 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex items-center gap-4">
        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 via-fuchsia-600 to-[#2a123f] font-bold text-white">
          {host[0]?.toUpperCase() ?? "J"}
        </div>
        <div className="min-w-0">
          <div className="text-white font-semibold">{host}</div>
          <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-400">
            <span>{currentPlayers}/2 joueurs</span>
            <span>Version {game.stateVersion}</span>
            <span>Pli {game.currentRound}/5</span>
          </div>
        </div>
      </div>
      <div className="flex items-center justify-between gap-3 sm:justify-end">
        <span className="flex items-center gap-2 rounded-full border border-yellow-300/30 bg-yellow-300/10 px-3 py-1 text-sm font-semibold text-yellow-200">
          <Clock className="w-4 h-4 animate-pulse" />
          En attente
        </span>
        <Button variant="app" size="sm" onClick={onJoin}>
          Rejoindre
        </Button>
      </div>
    </div>
  );
}

function MatchmakingScreen({
  stake,
  estimatedGain,
  queuedGame,
  onCancel,
}: {
  stake: number;
  estimatedGain: number;
  queuedGame: GameSummary | null;
  onCancel: () => void;
}) {
  return (
    <motion.div
      initial={{ opacity: 0, scale: 0.95 }}
      animate={{ opacity: 1, scale: 1 }}
      className="rounded-2xl border border-white/10 bg-[#151515]/88 p-8 text-center shadow-2xl shadow-black/30 backdrop-blur sm:p-12"
    >
      <motion.div
        animate={{ rotate: 360 }}
        transition={{ duration: 2, repeat: Infinity, ease: "linear" }}
        className="w-20 h-20 border-4 border-violet-500 border-t-transparent rounded-full mx-auto mb-6"
      />
      <h2 className="text-3xl font-bold text-white mb-2">Recherche d'adversaire...</h2>
      <p className="text-slate-400 mb-6">
        Mise indicative {stake} credits - gain potentiel {estimatedGain} credits
      </p>
      {queuedGame && (
        <p className="mx-auto mb-6 w-fit rounded-full border border-white/10 bg-black/30 px-3 py-1 text-sm text-slate-400">
          Table {queuedGame.id.slice(0, 8)}
        </p>
      )}
      <div className="mx-auto mb-8 grid max-w-md gap-2 text-left text-sm">
        <MatchmakingStep active label="Recherche d'une table compatible" />
        <MatchmakingStep active={Boolean(queuedGame)} label="Table reservee" />
        <MatchmakingStep active={false} label="Demarrage automatique de la partie" />
      </div>
      <div className="flex justify-center gap-2 mb-8">
        {[0, 0.2, 0.4].map((delay) => (
          <motion.div
            key={delay}
            animate={{ scale: [1, 1.2, 1] }}
            transition={{ duration: 1, repeat: Infinity, delay }}
            className="w-3 h-3 bg-violet-500 rounded-full"
          />
        ))}
      </div>
      <Button variant="appOutline" onClick={onCancel}>
        Annuler la recherche
      </Button>
    </motion.div>
  );
}

function MatchmakingStep({ active, label }: { active: boolean; label: string }) {
  return (
    <div
      className={`flex items-center gap-3 rounded-lg border px-3 py-2 ${
        active
          ? "border-violet-400/40 bg-violet-500/10 text-violet-100"
          : "border-white/10 bg-black/25 text-slate-400"
      }`}
    >
      <div className={`h-2.5 w-2.5 rounded-full ${active ? "bg-yellow-300" : "bg-slate-600"}`} />
      <span>{label}</span>
    </div>
  );
}
