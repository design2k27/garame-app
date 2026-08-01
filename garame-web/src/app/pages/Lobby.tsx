import { type ReactNode, useEffect, useState } from "react";
import { useNavigate } from "react-router";
import { Button } from "../components/Button";
import { motion } from "motion/react";
import {
  ArrowLeft,
  BookOpen,
  Clock,
  Coins,
  Lock,
  RefreshCw,
  ShieldCheck,
  Trophy,
  UserRoundSearch,
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
              <h1 className="app-logo text-2xl">GARAME</h1>
              <p className="text-sm text-slate-400">Salon de jeu · matchmaking compétitif</p>
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
              className="app-gold-frame relative mb-8 overflow-hidden rounded-3xl bg-gradient-to-br from-[#17121c] via-[#0e0e10] to-[#080809]"
            >
              <div className="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-violet-600/14 blur-3xl" />
              <div className="pointer-events-none absolute right-8 top-5 select-none text-8xl text-white/[0.018]">♦</div>
              <div className="grid gap-0 lg:grid-cols-[1.15fr_0.85fr]">
                <div className="relative p-6 sm:p-8 lg:p-10">
                  <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-violet-400/35 bg-violet-500/10 px-4 py-2 text-violet-200">
                    <Zap className="w-4 h-4" />
                    <span className="font-semibold">Match rapide</span>
                  </div>
                  <h2 className="mb-2 text-4xl font-black text-white">Jouer maintenant</h2>
                  <p className="max-w-2xl text-slate-400">
                    Choisissez votre table, vérifiez le gain potentiel puis trouvez un adversaire de votre niveau.
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
                          className={`relative overflow-hidden rounded-2xl border p-4 text-left transition-all ${
                            selectedStake === stake
                              ? "border-violet-300 bg-gradient-to-br from-violet-500/25 to-violet-950/25 shadow-[0_14px_38px_rgba(76,29,149,0.35)]"
                              : "border-white/10 bg-black/35 hover:border-violet-300/40 hover:bg-white/[0.035]"
                          }`}
                        >
                          <div className="flex items-center justify-between gap-2">
                            <div className="text-2xl font-black text-white">{stake}</div>
                            {selectedStake === stake && (
                              <div className="h-2.5 w-2.5 rounded-full bg-yellow-300 shadow-lg shadow-yellow-300/60" />
                            )}
                          </div>
                          <div className="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            crédits
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

                  <Button variant="app" size="lg" className="mt-7 w-full rounded-2xl py-5 text-lg shadow-[0_18px_45px_rgba(91,33,182,0.4)]" onClick={handleQuickMatch}>
                    <Zap className="w-5 h-5 mr-2 inline" />
                    {needsStakeConfirmation ? "Confirmer et jouer" : "Jouer maintenant"}
                  </Button>
                </div>

                <div className="relative border-t border-white/10 bg-black/35 p-6 sm:p-8 lg:border-l lg:border-t-0 lg:p-10">
                  <div className="app-eyebrow mb-4">
                    Résumé de la table
                  </div>
                  <div className="space-y-3">
                    <EconomyRow icon={<Coins className="h-4 w-4" />} label="Votre solde" value={`${user?.credits ?? 0} crédits`} />
                    <EconomyRow icon={<Lock className="h-4 w-4" />} label="Mise" value={`${selectedStake} crédits`} />
                    <EconomyRow icon={<ShieldCheck className="h-4 w-4" />} label="Frais estimés" value={`${estimatedFee} crédits`} />
                    <EconomyRow icon={<Trophy className="h-4 w-4" />} label="Gain potentiel" value={`${estimatedGain} crédits`} accent />
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

            <div className="app-gold-frame rounded-3xl bg-gradient-to-br from-[#141216] to-[#09090a] p-6 sm:p-8">
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
                    {isCreating ? "Création…" : "Créer"}
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
            playerName={user?.username ?? "Vous"}
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
  playerName,
  onCancel,
}: {
  stake: number;
  estimatedGain: number;
  queuedGame: GameSummary | null;
  playerName: string;
  onCancel: () => void;
}) {
  const [elapsedSeconds, setElapsedSeconds] = useState(0);
  const [showRules, setShowRules] = useState(false);

  useEffect(() => {
    const intervalId = window.setInterval(() => setElapsedSeconds((seconds) => seconds + 1), 1000);
    return () => window.clearInterval(intervalId);
  }, []);

  return (
    <motion.div
      initial={{ opacity: 0, scale: 0.95 }}
      animate={{ opacity: 1, scale: 1 }}
      className="app-gold-frame relative overflow-hidden rounded-[2rem] bg-[#09090a]/94 p-4 shadow-2xl shadow-black/50 backdrop-blur sm:p-8"
    >
      <div className="absolute left-1/2 top-32 h-80 w-80 -translate-x-1/2 rounded-full bg-violet-600/15 blur-3xl" />
      <div className="app-felt-surface absolute inset-x-4 bottom-4 top-44 rounded-[45%_45%_2rem_2rem/28%_28%_2rem_2rem] border border-[#d6b66f]/25 opacity-80 sm:inset-x-8" />

      <div className="relative text-center">
        <div className="inline-flex items-center gap-2 rounded-full border border-violet-300/25 bg-violet-500/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.16em] text-violet-200">
          <UserRoundSearch className="h-4 w-4" /> Matchmaking en cours
        </div>
        <h2 className="mt-4 text-3xl font-black text-white sm:text-4xl">Recherche d'un adversaire</h2>
        <p className="mt-2 text-sm text-neutral-400">Nous cherchons un joueur proche de votre niveau.</p>

        <div className="mx-auto mt-8 grid max-w-4xl grid-cols-[1fr_auto_1fr] items-center gap-3 px-2 py-8 sm:gap-10">
          <PlayerSearchCard name={playerName} known />
          <div className="relative flex h-20 w-20 items-center justify-center sm:h-32 sm:w-32">
            {[1, 0.72, 0.46].map((scale, index) => (
              <motion.div
                key={scale}
                animate={{ scale: [scale, scale + 0.12, scale], opacity: [0.3, 0.8, 0.3] }}
                transition={{ duration: 2.4, repeat: Infinity, delay: index * 0.25 }}
                className="absolute inset-0 rounded-full border border-violet-400/45"
              />
            ))}
            <span className="relative text-xl font-black text-yellow-200 sm:text-2xl">VS</span>
          </div>
          <PlayerSearchCard name="Recherche…" known={false} />
        </div>

        <div className="mx-auto mt-8 grid max-w-3xl gap-3 sm:grid-cols-3">
          <SearchInfo icon={<Clock className="h-4 w-4" />} label="Temps écoulé" value={formatElapsed(elapsedSeconds)} />
          <SearchInfo icon={<Coins className="h-4 w-4" />} label="Mise indicative" value={`${stake} crédits`} accent />
          <SearchInfo icon={<ShieldCheck className="h-4 w-4" />} label="Recherche" value="Votre niveau" />
        </div>

        <div className="mx-auto mt-4 max-w-3xl rounded-xl border border-white/10 bg-black/30 p-4 text-left">
          <div className="flex items-start gap-3">
            <div className="mt-0.5 h-2.5 w-2.5 shrink-0 animate-pulse rounded-full bg-violet-400 shadow-lg shadow-violet-400/50" />
            <div className="min-w-0 flex-1">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="font-semibold text-white">Recherche dans votre niveau</span>
                <span className="text-xs text-neutral-500">Estimation indicative : moins de 30 s</span>
              </div>
              <p className="mt-1 text-xs leading-relaxed text-neutral-400">
                La plage de recherche pourra s'élargir progressivement pour éviter une attente trop longue.
              </p>
            </div>
          </div>
        </div>

        {queuedGame && (
          <p className="mx-auto mt-3 w-fit text-xs text-neutral-600">
            Table sécurisée {queuedGame.id.slice(0, 8)} · démarrage automatique dès qu'un adversaire rejoint
          </p>
        )}

        <div className="mx-auto mt-6 max-w-3xl rounded-xl border border-violet-300/15 bg-violet-500/5 text-left">
          <button
            type="button"
            onClick={() => setShowRules((open) => !open)}
            className="flex w-full items-center justify-between gap-4 px-4 py-3 text-sm font-semibold text-violet-100"
            aria-expanded={showRules}
          >
            <span className="flex items-center gap-2"><BookOpen className="h-4 w-4" /> Revoir les règles pendant l'attente</span>
            <span className="text-xs text-violet-300">{showRules ? "Masquer" : "Afficher"}</span>
          </button>
          {showRules && (
            <div className="grid gap-2 border-t border-white/10 px-4 py-4 text-xs text-neutral-300 sm:grid-cols-3">
              <p><strong className="text-white">1.</strong> Suivez la couleur demandée si vous le pouvez.</p>
              <p><strong className="text-white">2.</strong> Le gagnant du cinquième pli remporte la partie.</p>
              <p><strong className="text-white">3.</strong> Somme ≤ 21 ou trois 7 : victoire immédiate.</p>
            </div>
          )}
        </div>

        <div className="mt-6 flex flex-col items-center justify-center gap-2 sm:flex-row">
          <Button variant="appOutline" onClick={onCancel} className="w-full sm:w-auto">
            Annuler la recherche
          </Button>
          <span className="text-xs text-neutral-500">Aucune pénalité en cas d'annulation</span>
        </div>
      </div>
    </motion.div>
  );
}

function PlayerSearchCard({ name, known }: { name: string; known: boolean }) {
  return (
    <div className={`mx-auto w-full max-w-[230px] rounded-3xl border p-5 shadow-2xl shadow-black/40 backdrop-blur sm:p-7 ${known ? "border-yellow-300/45 bg-gradient-to-b from-black/70 to-yellow-950/15" : "border-violet-300/30 bg-gradient-to-b from-black/65 to-violet-950/15"}`}>
      <div className={`mx-auto flex h-20 w-20 items-center justify-center rounded-full border text-2xl font-black shadow-2xl sm:h-24 sm:w-24 ${known ? "border-violet-200/40 bg-gradient-to-br from-violet-400 via-purple-600 to-violet-950 text-white shadow-violet-950/50" : "border-dashed border-violet-300/45 bg-black/45 text-violet-300"}`}>
        {known ? name.slice(0, 2).toUpperCase() : "?"}
      </div>
      <div className="mt-3 truncate font-bold text-white">{name}</div>
      <div className="mt-1 text-xs text-neutral-500">{known ? "Prêt à jouer" : "Analyse en cours"}</div>
    </div>
  );
}

function SearchInfo({ icon, label, value, accent = false }: { icon: ReactNode; label: string; value: string; accent?: boolean }) {
  return (
    <div className="rounded-xl border border-white/10 bg-black/30 p-3 text-left">
      <div className={`flex items-center gap-2 text-xs ${accent ? "text-yellow-300" : "text-violet-300"}`}>{icon}{label}</div>
      <div className={`mt-1 font-black ${accent ? "text-yellow-200" : "text-white"}`}>{value}</div>
    </div>
  );
}

function formatElapsed(seconds: number) {
  const minutes = Math.floor(seconds / 60).toString().padStart(2, "0");
  const remainder = (seconds % 60).toString().padStart(2, "0");
  return `${minutes}:${remainder}`;
}
