import { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router";
import { PlayingCard, type Suit, type CardValue } from "../components/PlayingCard";
import { Button } from "../components/Button";
import { motion, AnimatePresence } from "motion/react";
import { AlertTriangle, ChevronDown, Clock, Coins, History, RefreshCw, Shield, Trophy, X } from "lucide-react";
import {
  VictoryModal,
  DefeatModal,
  KoratModal,
  ThreeSevenModal,
  Under21Modal,
} from "../components/GameModals";
import { ApiError, getApiErrorMessage } from "../api/client";
import {
  ackGame,
  getGame,
  playCard,
  pollGame,
  requestRematch,
  startMatchmaking,
  syncGame,
} from "../api/garameApi";
import type { ApiCard, GameFull, GameResultPayload } from "../api/types";
import { useAuth } from "../auth/AuthContext";
import { toApiSuit, toUiCard } from "../game/cards";

type Card = { suit: Suit; value: CardValue; api: ApiCard };
type ModalType = "victory" | "defeat" | "korat" | "three7" | "under21" | null;

export function GameRoom() {
  const navigate = useNavigate();
  const { gameId } = useParams();
  const { token, user, refreshUser } = useAuth();
  const [game, setGame] = useState<GameFull | null>(null);
  const [result, setResult] = useState<GameResultPayload | null>(null);
  const [selectedCard, setSelectedCard] = useState<number | null>(null);
  const [activeModal, setActiveModal] = useState<ModalType>(null);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isPlaying, setIsPlaying] = useState(false);
  const [isResyncing, setIsResyncing] = useState(false);
  const [isRequestingRematch, setIsRequestingRematch] = useState(false);
  const [isStartingMatchmaking, setIsStartingMatchmaking] = useState(false);
  const [rematchWaiting, setRematchWaiting] = useState(false);
  const [modalActionError, setModalActionError] = useState<string | null>(null);
  const [isMobileHistoryOpen, setIsMobileHistoryOpen] = useState(false);

  const applyServerState = async (nextGame: GameFull, nextResult: GameResultPayload | null) => {
    setGame(nextGame);
    setResult(nextResult);
    setSelectedCard(null);

    if (nextGame.status !== "finished") {
      setActiveModal(null);
      setRematchWaiting(false);
      setModalActionError(null);
    }

    if (token && nextGame.stateVersion >= 1) {
      await ackGame(token, nextGame.id, nextGame.stateVersion).catch(() => undefined);
    }
  };

  useEffect(() => {
    if (!token || !gameId) return;

    let isMounted = true;
    setIsLoading(true);
    getGame(token, gameId)
      .then(async (response) => {
        if (!isMounted) return;
        await applyServerState(response.game, response.result);
        setError(null);
        setModalActionError(null);
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
  }, [gameId, token]);

  useEffect(() => {
    if (!token || !game || game.status === "finished") return;

    let timeoutId: number | undefined;
    let cancelled = false;

    const tick = async () => {
      try {
        const response = await pollGame(token, game.id, game.stateVersion);
        if (cancelled) return;

        if (response.changed && response.game) {
          await applyServerState(response.game, response.result);
        }

        timeoutId = window.setTimeout(tick, response.pollAfterMs ?? 1500);
      } catch {
        if (!cancelled) {
          timeoutId = window.setTimeout(tick, 2500);
        }
      }
    };

    timeoutId = window.setTimeout(tick, 1500);

    return () => {
      cancelled = true;
      if (timeoutId) window.clearTimeout(timeoutId);
    };
  }, [game?.id, game?.stateVersion, game?.status, token]);

  useEffect(() => {
    if (!game || game.status !== "finished" || !user) return;

    const didWin = game.winner === user.username;
    if (!didWin) {
      setActiveModal("defeat");
      return;
    }

    switch (game.winType) {
      case "korat":
        setActiveModal("korat");
        break;
      case "three_seven":
        setActiveModal("three7");
        break;
      case "moins_21":
        setActiveModal("under21");
        break;
      default:
        setActiveModal("victory");
    }

    refreshUser().catch(() => undefined);
  }, [game?.status, game?.winType, game?.winner, refreshUser, user]);

  const playerHand = useMemo<Card[]>(
    () => game?.myHand.map((card) => ({ ...toUiCard(card), api: card })) ?? [],
    [game?.myHand],
  );

  const currentTrick = useMemo<Card[]>(
    () =>
      game?.currentRoundData?.moves.map((move) => ({
        ...toUiCard(move.card),
        api: move.card,
      })) ?? [],
    [game?.currentRoundData?.moves],
  );

  const leadSuit = currentTrick[0]?.suit ?? null;
  const isMyTurn = Boolean(
    game &&
      user &&
      game.status === "playing" &&
      ((currentTrick.length === 0 && game.currentLeader === user.username) ||
        (currentTrick.length === 1 && currentTrick[0] && game.currentRoundData?.moves[0]?.player !== user.username)),
  );

  const canPlayCard = (card: Card) => {
    if (!isMyTurn) return false;
    if (!leadSuit) return true;
    if (playerHand.length === 1) return true;
    const hasLeadSuit = playerHand.some((c) => c.suit === leadSuit);
    return hasLeadSuit ? card.suit === leadSuit : true;
  };

  const handleCardClick = (index: number) => {
    const card = playerHand[index];
    if (!card) return;

    if (!isMyTurn) {
      setError("Ce n'est pas votre tour. Attendez le coup de l'adversaire.");
      return;
    }

    if (!canPlayCard(card)) {
      setError(
        leadSuit
          ? `Vous devez suivre la couleur demandee: ${getSuitLabel(leadSuit)}.`
          : "Cette carte ne peut pas etre jouee maintenant.",
      );
      return;
    }

    setError(null);
    if (selectedCard === index) {
      setSelectedCard(null);
    } else {
      setSelectedCard(index);
    }
  };

  const handlePlayCard = async () => {
    if (!token || !game || selectedCard === null) return;
    const card = playerHand[selectedCard];
    if (!card) return;

    setIsPlaying(true);
    setError(null);
    try {
      const response = await playCard(
        token,
        game.id,
        card.value,
        toApiSuit(card.suit),
        game.stateVersion,
      );
      await applyServerState(response.game, response.result);
    } catch (playError) {
      if (playError instanceof ApiError && playError.code === "out_of_sync") {
        await handleSync();
      } else {
        setError(getApiErrorMessage(playError));
      }
    } finally {
      setIsPlaying(false);
    }
  };

  const handleSync = async () => {
    if (!token || !game) return;

    setIsResyncing(true);
    try {
      const response = await syncGame(token, game.id, game.stateVersion);
      await applyServerState(response.game, response.result);
      setError(null);
    } catch (syncError) {
      setError(getApiErrorMessage(syncError));
    } finally {
      setIsResyncing(false);
    }
  };

  const handleCloseModal = () => setActiveModal(null);
  const handleDashboard = () => navigate("/dashboard");

  const handleRematch = async () => {
    if (!token || !game) return;

    setIsRequestingRematch(true);
    setError(null);
    setModalActionError(null);
    try {
      const response = await requestRematch(token, game.id);
      if (response.status === "started" && response.game) {
        setActiveModal(null);
        setRematchWaiting(false);
        setModalActionError(null);
        setGame(response.game);
        setResult(response.result);
        navigate(`/game/${response.game.id}`);
        return;
      }

      setRematchWaiting(true);
    } catch (rematchError) {
      const message = getApiErrorMessage(rematchError);
      setError(message);
      setModalActionError(message);
    } finally {
      setIsRequestingRematch(false);
    }
  };

  const handleMatchmaking = async () => {
    if (!token) return;

    setIsStartingMatchmaking(true);
    setError(null);
    setModalActionError(null);
    try {
      const response = await startMatchmaking(token);
      if (response.action === "joined" || response.game.status !== "waiting") {
        setActiveModal(null);
        setRematchWaiting(false);
        setModalActionError(null);
        navigate(`/game/${response.game.id}`);
        return;
      }

      navigate("/lobby");
    } catch (matchmakingError) {
      const message = getApiErrorMessage(matchmakingError);
      setError(message);
      setModalActionError(message);
    } finally {
      setIsStartingMatchmaking(false);
    }
  };

  useEffect(() => {
    if (!token || !game || !rematchWaiting) return;

    let timeoutId: number | undefined;
    let cancelled = false;

    const tick = async () => {
      try {
        const response = await requestRematch(token, game.id);
        if (cancelled) return;

        if (response.status === "started" && response.game) {
          setActiveModal(null);
          setRematchWaiting(false);
          setModalActionError(null);
          setGame(response.game);
          setResult(response.result);
          navigate(`/game/${response.game.id}`);
          return;
        }

        timeoutId = window.setTimeout(tick, 1500);
      } catch {
        if (!cancelled) {
          timeoutId = window.setTimeout(tick, 2500);
        }
      }
    };

    timeoutId = window.setTimeout(tick, 1500);

    return () => {
      cancelled = true;
      if (timeoutId) window.clearTimeout(timeoutId);
    };
  }, [game, navigate, rematchWaiting, token]);

  if (isLoading) {
    return (
      <div className="app-page px-4 py-6 text-neutral-300">
        <div className="mx-auto flex min-h-[80vh] max-w-4xl flex-col justify-center gap-6">
          <div className="h-12 w-48 animate-pulse rounded-lg bg-white/10" />
          <div className="grid gap-4 md:grid-cols-3">
            <div className="h-24 animate-pulse rounded-lg bg-white/[0.08]" />
            <div className="h-24 animate-pulse rounded-lg bg-white/[0.08]" />
            <div className="h-24 animate-pulse rounded-lg bg-white/[0.08]" />
          </div>
          <div className="h-72 animate-pulse rounded-xl border border-white/10 bg-[#151515]/70" />
          <div className="text-center text-sm text-neutral-400">Chargement de la partie...</div>
        </div>
      </div>
    );
  }

  if (!game) {
    return (
      <div className="app-page flex items-center justify-center px-4">
        <div className="max-w-md text-center">
          <div className="text-red-300 mb-4">{error ?? "Partie introuvable."}</div>
          <Button variant="app" onClick={() => navigate("/lobby")}>Retour au lobby</Button>
        </div>
      </div>
    );
  }

  const opponentCardsCount = game.opponent?.cardsLeft ?? 0;
  const pot = game.winType === "korat" ? 20 : 10;
  const myName = user?.username ?? "Vous";
  const opponentName = game.opponent?.username ?? "En attente";
  const opponentTricksWon = game.opponent?.tricksWon ?? 0;
  const currentRoundLabel = Math.min(Math.max(game.currentRound, 1), 5);
  const modalAmount = game.winType === "korat" ? 20 : 10;
  const rematchLabel = rematchWaiting
    ? "En attente de l'adversaire..."
    : isRequestingRematch
      ? "Demande en cours..."
      : "Demander une revanche";
  const matchmakingLabel = isStartingMatchmaking ? "Lancement..." : "Nouveau matchmaking";
  const modalActionMessage = rematchWaiting
    ? "Demande envoyee. La partie redemarre quand l'adversaire demande aussi une revanche."
    : null;
  const rematchDisabled = isRequestingRematch || rematchWaiting || isStartingMatchmaking;
  const matchmakingDisabled = isRequestingRematch || isStartingMatchmaking;
  const selectedPlayableCard = selectedCard !== null ? playerHand[selectedCard] : null;
  const playableCardsCount = playerHand.filter((card) => canPlayCard(card)).length;
  const turnState = getTurnState({
    status: game.status,
    isMyTurn,
    leadSuit,
    currentTrickCount: currentTrick.length,
    selectedCard: selectedPlayableCard,
    playableCardsCount,
    opponentName: game.opponent?.username ?? "l'adversaire",
  });

  return (
    <div className="app-page relative overflow-x-hidden">
      <div className="absolute inset-0">
        <div className="absolute inset-0 bg-gradient-to-br from-violet-950/35 via-[#111111]/45 to-black/70" />
        <div
          className="absolute inset-0"
          style={{
            backgroundImage: `
              radial-gradient(circle at 50% 50%, rgba(124, 58, 237, 0.18) 0%, transparent 60%),
              radial-gradient(circle at 28% 38%, rgba(253, 224, 71, 0.10) 0%, transparent 36%),
              radial-gradient(circle at 72% 62%, rgba(168, 85, 247, 0.12) 0%, transparent 42%)
            `,
          }}
        />
        <div
          className="absolute inset-0 opacity-5"
          style={{
            backgroundImage: `repeating-linear-gradient(
              45deg,
              transparent,
              transparent 2px,
              rgba(255,255,255,0.03) 2px,
              rgba(255,255,255,0.03) 4px
            )`,
          }}
        />
      </div>

      <div className="relative z-10 min-h-screen flex flex-col">
        <div className="p-4">
          <div className="mx-auto flex max-w-5xl flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <Button
              variant="appOutline"
              size="sm"
              onClick={() => navigate("/dashboard")}
              className="w-fit"
            >
              <X className="w-4 h-4 mr-2 inline" />
              Quitter
            </Button>

            <div className="grid flex-1 grid-cols-2 gap-2 sm:grid-cols-4">
              <StatusTile label="Gain potentiel" value={`${pot} credits`} tone="amber" />
              <StatusTile label="Score" value={`${game.myTricksWon} - ${opponentTricksWon}`} tone="slate" />
              <StatusTile label="Pli" value={`${currentRoundLabel}/5`} tone="emerald" />
              <StatusTile label="Solde" value={`${user?.credits ?? 0} credits`} tone="slate" />
            </div>

            {isResyncing && (
              <div className="w-fit rounded-lg border border-violet-300/35 bg-violet-500/10 px-3 py-2 text-sm font-semibold text-violet-100">
                resync
              </div>
            )}
          </div>
        </div>

        <TrickHistoryPanel game={game} myName={myName} className="hidden xl:flex" />

        {error && (
          <ErrorBanner
            message={error}
            onRetry={handleSync}
            onDismiss={() => setError(null)}
            onLobby={() => navigate("/lobby")}
            isRetrying={isResyncing}
          />
        )}

        <div className="px-4 pt-4">
          <div className="max-w-4xl mx-auto">
            <div className="flex items-center justify-between mb-4">
              <PlayerSummary
                name={opponentName}
                credits={game.opponent?.credits ?? 0}
                tricksWon={opponentTricksWon}
                cardsLeft={opponentCardsCount}
                tone="opponent"
              />
              <div className={`flex items-center gap-2 rounded-lg border px-4 py-3 backdrop-blur ${turnState.headerClass}`}>
                <Clock className={`w-5 h-5 ${isMyTurn ? "animate-pulse" : ""}`} />
                <span className="font-bold text-lg">{turnState.shortLabel}</span>
              </div>
            </div>

            <div className="flex min-h-32 justify-center gap-2 rounded-xl border border-white/10 bg-black/30 px-3 py-4 shadow-inner shadow-black/30">
              {opponentCardsCount > 0 ? (
                Array.from({ length: opponentCardsCount }).map((_, i) => (
                  <motion.div
                    key={i}
                    initial={{ opacity: 0, y: -20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: i * 0.05 }}
                  >
                    <PlayingCard suit="spades" value={8} faceDown size="md" backSkin="gold" />
                  </motion.div>
                ))
              ) : (
                <EmptyTableMessage
                  title={game.status === "waiting" ? "Aucun adversaire" : "Plus de cartes adverses"}
                  description={game.status === "waiting" ? "La table attend un second joueur." : "La manche touche a sa fin."}
                />
              )}
            </div>
          </div>
        </div>

        <div className="flex-1 flex items-center justify-center px-4">
          <div className="relative w-full max-w-4xl">
            <div className="absolute inset-0 -m-32 rounded-full bg-gradient-to-br from-violet-700/24 via-yellow-300/10 to-black/20 blur-3xl" />

            <div className={`relative z-20 mx-auto mb-5 max-w-2xl rounded-xl border px-5 py-4 text-center shadow-2xl backdrop-blur ${turnState.panelClass}`}>
              <div className="text-xs font-bold uppercase tracking-[0.22em] opacity-80">Tour en cours</div>
              <div className="mt-1 text-2xl font-black text-white">{turnState.title}</div>
              <div className="mt-1 text-sm text-neutral-200">{turnState.description}</div>
              <div className="mt-3 flex flex-wrap items-center justify-center gap-2">
                {leadSuit ? (
                  <SuitDemandBadge suit={leadSuit} />
                ) : (
                  <span className="rounded-full border border-white/10 bg-black/50 px-3 py-1 text-xs font-semibold text-neutral-300">
                    Aucune couleur demandee
                  </span>
                )}
                {isMyTurn && (
                  <span className="rounded-full border border-violet-300/40 bg-violet-500/12 px-3 py-1 text-xs font-semibold text-violet-100">
                    {playableCardsCount} carte{playableCardsCount > 1 ? "s" : ""} jouable{playableCardsCount > 1 ? "s" : ""}
                  </span>
                )}
              </div>
            </div>

            <div className="relative z-10 flex min-h-[220px] items-center justify-center gap-4 rounded-2xl border border-violet-300/20 bg-gradient-to-br from-[#151515]/88 via-black/70 to-violet-950/28 px-4 py-8 shadow-2xl shadow-black/40">
              <AnimatePresence>
                {currentTrick.map((card, i) => (
                  <motion.div
                    key={`${card.suit}-${card.value}-${i}`}
                    initial={{ scale: 0, rotate: -180 }}
                    animate={{ scale: 1, rotate: 0 }}
                    exit={{ scale: 0, opacity: 0 }}
                  >
                    <PlayingCard suit={card.suit} value={card.value} size="lg" />
                  </motion.div>
                ))}
              </AnimatePresence>
              {currentTrick.length === 0 && (
                <EmptyTableMessage
                  title={game.status === "waiting" ? "En attente d'un adversaire" : "Le pli est vide"}
                  description={game.status === "waiting" ? "La partie demarrera automatiquement." : "La premiere carte fixera la couleur demandee."}
                />
              )}
            </div>

            <div className="text-center mt-4">
              {result?.roundWinner && (
                <div className="inline-flex items-center gap-2 rounded-full border border-yellow-300/40 bg-yellow-300/10 px-4 py-2 text-sm font-semibold text-yellow-100">
                  <Trophy className="h-4 w-4" />
                  Pli remporte par {result.roundWinner === myName ? "vous" : result.roundWinner}
                </div>
              )}
            </div>
          </div>
        </div>

        <div className="sticky bottom-0 z-30 border-t border-white/10 bg-[#080808]/92 px-4 pb-4 pt-3 backdrop-blur-xl sm:relative sm:border-t-0 sm:bg-transparent sm:pt-0 sm:backdrop-blur-none">
          <div className="max-w-4xl mx-auto">
            <div className="mb-4 flex min-h-36 justify-start gap-3 overflow-x-auto pb-2 sm:justify-center sm:overflow-visible sm:pb-0">
              {playerHand.map((card, i) => (
                <div
                  key={`${card.suit}-${card.value}-${i}`}
                  onClick={() => handleCardClick(i)}
                  className="shrink-0"
                >
                  <PlayingCard
                    suit={card.suit}
                    value={card.value}
                    isPlayable={canPlayCard(card)}
                    isSelected={selectedCard === i}
                    isHighlighted={isMyTurn && canPlayCard(card) && selectedCard !== i}
                  />
                </div>
              ))}
            </div>

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <PlayerSummary
                name={myName}
                credits={user?.credits ?? 0}
                tricksWon={game.myTricksWon}
                cardsLeft={playerHand.length}
                tone="player"
                stateVersion={game.stateVersion}
              />

              <Button
                variant="app"
                size="lg"
                disabled={selectedCard === null || !isMyTurn || isPlaying}
                onClick={handlePlayCard}
                className={`w-full sm:w-auto ${selectedCard !== null && isMyTurn ? "shadow-lg shadow-violet-500/35" : ""}`}
              >
                {isPlaying
                  ? "Carte en cours..."
                  : selectedPlayableCard
                    ? `Jouer ${selectedPlayableCard.value}${getSuitSymbol(selectedPlayableCard.suit)}`
                    : isMyTurn
                      ? "Selectionnez une carte"
                      : "En attente"}
              </Button>
            </div>
          </div>
        </div>

        <div className="xl:hidden px-4 pb-4">
          <div className="max-w-4xl mx-auto">
            <button
              type="button"
              onClick={() => setIsMobileHistoryOpen((open) => !open)}
              className="flex w-full items-center justify-between rounded-lg border border-white/10 bg-[#151515]/90 px-4 py-3 text-left text-white shadow-lg shadow-black/25"
            >
              <span className="flex items-center gap-2 font-semibold">
                <History className="h-4 w-4 text-yellow-300" />
                Historique des plis
              </span>
              <span className="flex items-center gap-2 text-xs text-neutral-400">
                {game.rounds.filter((round) => round.moves.length > 0).length}/5
                <ChevronDown className={`h-4 w-4 transition-transform ${isMobileHistoryOpen ? "rotate-180" : ""}`} />
              </span>
            </button>
            {isMobileHistoryOpen && (
              <div className="mt-3">
                <TrickHistoryPanel game={game} myName={myName} compact />
              </div>
            )}
          </div>
        </div>
      </div>

      <VictoryModal
        isOpen={activeModal === "victory"}
        onClose={handleCloseModal}
        onRematch={handleRematch}
        onMatchmaking={handleMatchmaking}
        onDashboard={handleDashboard}
        amount={modalAmount}
        rematchLabel={rematchLabel}
        rematchDisabled={rematchDisabled}
        matchmakingLabel={matchmakingLabel}
        matchmakingDisabled={matchmakingDisabled}
        actionMessage={modalActionMessage}
        actionError={modalActionError}
      />
      <DefeatModal
        isOpen={activeModal === "defeat"}
        onClose={handleCloseModal}
        onRematch={handleRematch}
        onMatchmaking={handleMatchmaking}
        onDashboard={handleDashboard}
        amount={modalAmount}
        rematchLabel={rematchLabel}
        rematchDisabled={rematchDisabled}
        matchmakingLabel={matchmakingLabel}
        matchmakingDisabled={matchmakingDisabled}
        actionMessage={modalActionMessage}
        actionError={modalActionError}
      />
      <KoratModal
        isOpen={activeModal === "korat"}
        onClose={handleCloseModal}
        onRematch={handleRematch}
        onMatchmaking={handleMatchmaking}
        onDashboard={handleDashboard}
        amount={10}
        rematchLabel={rematchLabel}
        rematchDisabled={rematchDisabled}
        matchmakingLabel={matchmakingLabel}
        matchmakingDisabled={matchmakingDisabled}
        actionMessage={modalActionMessage}
        actionError={modalActionError}
      />
      <ThreeSevenModal
        isOpen={activeModal === "three7"}
        onClose={handleCloseModal}
        onRematch={handleRematch}
        onMatchmaking={handleMatchmaking}
        onDashboard={handleDashboard}
        amount={modalAmount}
        rematchLabel={rematchLabel}
        rematchDisabled={rematchDisabled}
        matchmakingLabel={matchmakingLabel}
        matchmakingDisabled={matchmakingDisabled}
        actionMessage={modalActionMessage}
        actionError={modalActionError}
      />
      <Under21Modal
        isOpen={activeModal === "under21"}
        onClose={handleCloseModal}
        onRematch={handleRematch}
        onMatchmaking={handleMatchmaking}
        onDashboard={handleDashboard}
        amount={modalAmount}
        rematchLabel={rematchLabel}
        rematchDisabled={rematchDisabled}
        matchmakingLabel={matchmakingLabel}
        matchmakingDisabled={matchmakingDisabled}
        actionMessage={modalActionMessage}
        actionError={modalActionError}
      />
    </div>
  );
}

function StatusTile({
  label,
  value,
  tone = "slate",
}: {
  label: string;
  value: string;
  tone?: "slate" | "amber" | "emerald";
}) {
  const toneClass = {
    slate: "border-white/10 bg-[#151515]/85 text-white",
    amber: "border-yellow-300/40 bg-yellow-300/10 text-yellow-100",
    emerald: "border-violet-300/40 bg-violet-500/12 text-violet-100",
  }[tone];

  return (
    <div className={`rounded-lg border px-3 py-2 backdrop-blur ${toneClass}`}>
      <div className="truncate text-[11px] font-semibold uppercase tracking-wide text-neutral-400">
        {label}
      </div>
      <div className="truncate text-base font-black sm:text-lg">{value}</div>
    </div>
  );
}

function PlayerSummary({
  name,
  credits,
  tricksWon,
  cardsLeft,
  tone,
  stateVersion,
}: {
  name: string;
  credits: number;
  tricksWon: number;
  cardsLeft: number;
  tone: "player" | "opponent";
  stateVersion?: number;
}) {
  const isPlayer = tone === "player";

  return (
    <div
      className={`
        flex min-w-0 items-center gap-3 rounded-lg border px-4 py-3 backdrop-blur
        ${isPlayer ? "border-yellow-300/55 bg-[#151515]/90" : "border-violet-300/25 bg-[#101010]/86"}
      `}
    >
      <div
        className={`
          flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-black text-white
          ${isPlayer ? "bg-gradient-to-r from-yellow-300 to-yellow-500 text-black" : "bg-gradient-to-r from-violet-500 to-purple-700"}
        `}
      >
        {name.slice(0, 2).toUpperCase()}
      </div>
      <div className="min-w-0">
        <div className="truncate font-semibold text-white">{name}</div>
        <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-neutral-400">
          <span className="inline-flex items-center gap-1">
            <Coins className="h-3.5 w-3.5 text-yellow-300" />
            {credits} credits
          </span>
          <span className="inline-flex items-center gap-1">
            <Trophy className="h-3.5 w-3.5 text-yellow-200" />
            {tricksWon} pli{tricksWon > 1 ? "s" : ""}
          </span>
          <span className="inline-flex items-center gap-1">
            <Shield className="h-3.5 w-3.5 text-violet-200" />
            {cardsLeft} carte{cardsLeft > 1 ? "s" : ""}
          </span>
          {stateVersion ? <span className="text-neutral-500">v{stateVersion}</span> : null}
        </div>
      </div>
    </div>
  );
}

function EmptyTableMessage({ title, description }: { title: string; description: string }) {
  return (
    <div className="rounded-xl border border-dashed border-white/12 bg-black/45 px-8 py-6 text-center text-neutral-400">
      <div className="text-sm font-semibold text-neutral-200">{title}</div>
      <div className="mt-1 text-xs">{description}</div>
    </div>
  );
}

function ErrorBanner({
  message,
  onRetry,
  onDismiss,
  onLobby,
  isRetrying,
}: {
  message: string;
  onRetry: () => void;
  onDismiss: () => void;
  onLobby: () => void;
  isRetrying: boolean;
}) {
  const kind = getErrorKind(message);

  return (
    <div className="mx-auto w-full max-w-4xl px-4">
      <div className="rounded-xl border border-red-500/40 bg-red-500/10 p-4 text-red-100 shadow-lg shadow-red-950/20">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex min-w-0 gap-3">
            <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-red-300" />
            <div className="min-w-0">
              <div className="font-semibold">{kind.title}</div>
              <div className="mt-1 text-sm text-red-100/85">{message}</div>
              <div className="mt-1 text-xs text-red-100/65">{kind.hint}</div>
            </div>
          </div>
          <div className="flex shrink-0 flex-wrap gap-2">
            <button
              type="button"
              onClick={onRetry}
              disabled={isRetrying}
              className="inline-flex items-center gap-1 rounded-md border border-red-300/30 bg-red-950/30 px-3 py-2 text-xs font-semibold text-red-50 disabled:opacity-60"
            >
              <RefreshCw className={`h-3.5 w-3.5 ${isRetrying ? "animate-spin" : ""}`} />
              Resync
            </button>
            <button
              type="button"
              onClick={onLobby}
              className="rounded-md border border-red-300/30 bg-red-950/30 px-3 py-2 text-xs font-semibold text-red-50"
            >
              Lobby
            </button>
            <button
              type="button"
              onClick={onDismiss}
              className="rounded-md px-3 py-2 text-xs font-semibold text-red-100/75 hover:text-white"
            >
              Fermer
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

function getErrorKind(message: string) {
  const normalized = message.toLowerCase();
  if (normalized.includes("sync") || normalized.includes("version")) {
    return {
      title: "Etat de partie desynchronise",
      hint: "Resynchronisez la partie avant de rejouer une carte.",
    };
  }
  if (normalized.includes("tour") || normalized.includes("couleur") || normalized.includes("carte")) {
    return {
      title: "Action impossible",
      hint: "Verifiez le tour en cours et les cartes autorisees.",
    };
  }
  if (normalized.includes("network") || normalized.includes("fetch") || normalized.includes("reseau")) {
    return {
      title: "Connexion instable",
      hint: "La resynchronisation permet de recuperer le dernier etat connu.",
    };
  }

  return {
    title: "Erreur de partie",
    hint: "Vous pouvez resynchroniser ou revenir au lobby.",
  };
}

function getTurnState({
  status,
  isMyTurn,
  leadSuit,
  currentTrickCount,
  selectedCard,
  playableCardsCount,
  opponentName,
}: {
  status: GameFull["status"];
  isMyTurn: boolean;
  leadSuit: Suit | null;
  currentTrickCount: number;
  selectedCard: Card | null;
  playableCardsCount: number;
  opponentName: string;
}) {
  if (status === "waiting") {
    return {
      shortLabel: "Attente",
      title: "En attente d'un adversaire",
      description: "La partie demarrera automatiquement quand un joueur rejoint la table.",
      headerClass: "border-white/10 bg-[#151515]/85 text-neutral-200",
      panelClass: "border-white/10 bg-[#151515]/90",
    };
  }

  if (isMyTurn) {
    const leadText = leadSuit
      ? `Suivez ${getSuitLabel(leadSuit)} si vous avez cette couleur.`
      : "Vous ouvrez le pli, choisissez la couleur avec votre premiere carte.";
    const selectionText = selectedCard
      ? `Carte selectionnee: ${selectedCard.value}${getSuitSymbol(selectedCard.suit)}.`
      : `${playableCardsCount} carte${playableCardsCount > 1 ? "s" : ""} disponible${playableCardsCount > 1 ? "s" : ""}.`;

    return {
      shortLabel: "A vous",
      title: "A vous de jouer",
      description: `${leadText} ${selectionText}`,
      headerClass: "border-violet-300/50 bg-violet-500/16 text-violet-100 shadow-lg shadow-violet-950/35",
      panelClass: "border-violet-300/50 bg-gradient-to-br from-violet-500/20 via-[#151515]/92 to-black/95",
    };
  }

  return {
    shortLabel: "Adversaire",
    title: `${opponentName} doit jouer`,
    description:
      currentTrickCount === 0
        ? "L'adversaire ouvre ce pli. La couleur demandee apparaitra apres son coup."
        : "Attendez la deuxieme carte pour connaitre le gagnant du pli.",
    headerClass: "border-yellow-300/40 bg-yellow-300/10 text-yellow-100",
    panelClass: "border-yellow-300/40 bg-gradient-to-br from-yellow-300/14 via-[#151515]/92 to-black/95",
  };
}

function TrickHistoryPanel({
  game,
  myName,
  compact = false,
  className = "",
}: {
  game: GameFull;
  myName: string;
  compact?: boolean;
  className?: string;
}) {
  const rounds = [...game.rounds].sort((a, b) => a.number - b.number);
  const completedRounds = rounds.filter((round) => round.moves.length > 0);
  const myWonRounds = completedRounds.filter((round) => round.winner === myName).length;
  const opponentWonRounds = completedRounds.filter((round) => round.winner && round.winner !== myName).length;

  return (
    <aside
      className={`
        ${compact ? "relative flex w-full max-h-56" : "absolute right-4 top-24 bottom-4 w-80"}
        ${className}
        flex-col rounded-lg border border-white/10 bg-[#0b0b0c]/90 backdrop-blur
        shadow-2xl shadow-black/30 overflow-hidden
      `}
    >
      <div className="flex items-center justify-between gap-3 border-b border-white/10 bg-[#151515]/80 px-4 py-3">
        <div className="flex items-center gap-2 text-white font-semibold">
          <History className="w-4 h-4 text-yellow-300" />
          Historique des plis
        </div>
        <div className="flex items-center gap-2">
          <div className="rounded-full border border-yellow-300/35 bg-yellow-300/10 px-2 py-0.5 text-xs font-semibold text-yellow-100">
            Vous {myWonRounds} - {opponentWonRounds}
          </div>
          <div className="rounded-full border border-white/10 bg-black/55 px-2 py-0.5 text-xs font-semibold text-neutral-300">
            {completedRounds.length}/5
          </div>
        </div>
      </div>

      <div className="flex-1 overflow-y-auto p-3 space-y-3">
        {completedRounds.length === 0 ? (
          <div className="rounded-lg border border-white/10 bg-[#151515]/75 p-4 text-sm text-neutral-400">
            Aucun pli joue.
          </div>
        ) : (
          completedRounds.map((round) => {
            const leadCard = round.moves[0]?.card;
            const didWinRound = round.winner === myName;

            return (
              <div
                key={round.number}
                className={`
                  rounded-lg border p-3 shadow-lg
                  ${didWinRound
                    ? "border-yellow-300/55 bg-gradient-to-br from-yellow-300/13 via-[#151515] to-black shadow-yellow-950/20"
                    : "border-white/10 bg-[#151515]/78 shadow-black/20"}
                `}
              >
                <div className="flex items-center justify-between gap-2 mb-3">
                  <div className="flex items-center gap-2">
                    <div className="flex h-7 w-7 items-center justify-center rounded-md border border-violet-300/25 bg-violet-500/12 text-sm font-bold text-white">
                      {round.number}
                    </div>
                    <div>
                      <div className="text-sm font-semibold text-white">Pli {round.number}</div>
                      <div className="text-[11px] text-neutral-500">
                        {round.moves.length}/2 cartes
                      </div>
                    </div>
                  </div>
                  {round.winner ? (
                    <div className="flex shrink-0 items-center gap-1 rounded-full border border-yellow-300/35 bg-yellow-300/10 px-2 py-1 text-xs font-semibold text-yellow-100">
                      <Trophy className="w-3.5 h-3.5" />
                      {round.winner === myName ? "Vous" : round.winner}
                    </div>
                  ) : (
                    <div className="shrink-0 rounded-full border border-white/10 bg-black/55 px-2 py-1 text-xs text-neutral-400">
                      En cours
                    </div>
                  )}
                </div>

                {leadCard ? (
                  <div className="mb-3 flex items-center justify-between gap-2 rounded-md border border-white/10 bg-black/45 px-2.5 py-2 text-xs">
                    <span className="text-neutral-400">Couleur demandee</span>
                    <SuitBadge suit={leadCard.suit} />
                  </div>
                ) : null}

                <div className="rounded-lg border border-white/10 bg-black/45 p-2">
                  <div className="flex items-stretch justify-center gap-2">
                    {round.moves.map((move) => {
                      const didWinMove = move.player === round.winner;

                      return (
                        <div
                          key={`${round.number}-${move.playOrder}-${move.player}`}
                          className={`
                            relative flex min-w-0 flex-1 flex-col items-center gap-2 rounded-lg border px-2 py-2
                            ${didWinMove
                              ? "border-yellow-300/45 bg-yellow-300/10"
                              : "border-white/10 bg-[#151515]/70"}
                          `}
                        >
                          {didWinMove && (
                            <div className="absolute -top-2 rounded-full border border-yellow-200/60 bg-yellow-300 px-1.5 py-0.5 text-[10px] font-black text-black shadow">
                              Gagne
                            </div>
                          )}
                          <MiniCard card={move.card} isWinner={didWinMove} />
                          <div className="w-full min-w-0 text-center">
                            <div className="truncate text-xs font-semibold text-neutral-100">
                              {move.player === myName ? "Vous" : move.player}
                            </div>
                            <div className="text-[10px] uppercase tracking-wide text-neutral-500">
                              {move.playOrder === 1 ? "Ouverture" : "Reponse"}
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                  {round.moves.length === 1 && (
                    <div className="mt-2 rounded-md border border-dashed border-white/10 px-3 py-2 text-center text-xs text-neutral-500">
                      En attente de la deuxieme carte
                    </div>
                  )}
                </div>
              </div>
            );
          })
        )}
      </div>
    </aside>
  );
}

function SuitDemandBadge({ suit }: { suit: Suit }) {
  const isRed = suit === "hearts" || suit === "diamonds";

  return (
    <span
      className={`
        inline-flex items-center gap-2 rounded-full border bg-white px-3 py-1 text-xs font-black shadow
        ${isRed ? "border-red-200 text-red-600" : "border-slate-300 text-slate-950"}
      `}
    >
      <span className="text-base leading-none">{getSuitSymbol(suit)}</span>
      <span>Couleur demandee: {getSuitLabel(suit)}</span>
    </span>
  );
}

function SuitBadge({ suit }: { suit: ApiCard["suit"] }) {
  const isRed = suit === "H" || suit === "D";

  return (
    <span
      className={`
        inline-flex items-center gap-1 rounded-full border bg-white px-2 py-1 font-bold shadow-sm
        ${isRed ? "border-red-200 text-red-600" : "border-slate-300 text-slate-900"}
      `}
    >
      <span className="text-sm leading-none">{getApiSuitSymbol(suit)}</span>
      <span>{getApiSuitLabel(suit)}</span>
    </span>
  );
}

function MiniCard({ card, isWinner = false }: { card: ApiCard; isWinner?: boolean }) {
  const isRed = card.suit === "H" || card.suit === "D";
  const suitClass = isRed ? "text-red-600" : "text-slate-900";

  return (
    <div
      className={`
        relative h-20 w-14 shrink-0 overflow-hidden rounded-md border-2 bg-white shadow-lg shadow-black/30
        ${isWinner ? "border-yellow-300 ring-2 ring-yellow-300/50" : "border-slate-200"}
      `}
    >
      <div className="absolute inset-0 rounded-md bg-gradient-to-br from-white via-slate-50 to-white" />
      <div className={`absolute left-1 top-1 z-10 flex flex-col items-center text-sm font-black leading-none ${suitClass}`}>
        <span>{card.value}</span>
        <span className="text-base">{getApiSuitSymbol(card.suit)}</span>
      </div>
      <div className={`absolute inset-0 z-10 flex items-center justify-center text-4xl leading-none ${suitClass}`}>
        {getApiSuitSymbol(card.suit)}
      </div>
      <div className={`absolute bottom-1 right-1 z-10 flex rotate-180 flex-col items-center text-sm font-black leading-none ${suitClass}`}>
        <span>{card.value}</span>
        <span className="text-base">{getApiSuitSymbol(card.suit)}</span>
      </div>
      <div className="absolute inset-0 rounded-md bg-gradient-to-br from-white/60 via-transparent to-transparent" />
    </div>
  );
}

function getApiSuitLabel(suit: ApiCard["suit"]) {
  switch (suit) {
    case "H":
      return "Coeur";
    case "D":
      return "Carreau";
    case "C":
      return "Trefle";
    case "S":
      return "Pique";
  }
}

function getApiSuitSymbol(suit: ApiCard["suit"]) {
  switch (suit) {
    case "H":
      return "♥";
    case "D":
      return "♦";
    case "C":
      return "♣";
    case "S":
      return "♠";
  }
}

function getSuitLabel(suit: Suit) {
  switch (suit) {
    case "hearts":
      return "Coeur";
    case "diamonds":
      return "Carreau";
    case "clubs":
      return "Trefle";
    case "spades":
      return "Pique";
  }
}

function getSuitSymbol(suit: Suit) {
  switch (suit) {
    case "hearts":
      return "♥";
    case "diamonds":
      return "♦";
    case "clubs":
      return "♣";
    case "spades":
      return "♠";
  }
}

function SuitSymbol({ suit }: { suit: Suit }) {
  const color = suit === "hearts" || suit === "diamonds" ? "#DC2626" : "#0F172A";
  const symbol = {
    hearts: "♥",
    diamonds: "♦",
    clubs: "♣",
    spades: "♠",
  }[suit];

  return <span style={{ color }}>{symbol}</span>;
}
