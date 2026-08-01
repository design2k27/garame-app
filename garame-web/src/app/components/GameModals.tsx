import { motion, AnimatePresence } from "motion/react";
import { Button } from "./Button";
import { Trophy, X, Sparkles, Target } from "lucide-react";
import confetti from "canvas-confetti";
import { useEffect } from "react";

interface ModalProps {
  isOpen: boolean;
  onClose: () => void;
  onRematch: () => void;
  onMatchmaking: () => void;
  onDashboard: () => void;
  amount: number;
  rematchLabel?: string;
  rematchDisabled?: boolean;
  matchmakingLabel?: string;
  matchmakingDisabled?: boolean;
  actionMessage?: string | null;
  actionError?: string | null;
}

function ActionStatus({
  message,
  error,
}: {
  message?: string | null;
  error?: string | null;
}) {
  if (!message && !error) return null;

  return (
    <div className="mb-4 space-y-2">
      {message && (
        <div className="rounded-lg border border-violet-300/40 bg-violet-500/10 px-3 py-2 text-sm text-violet-100">
          {message}
        </div>
      )}
      {error && (
        <div className="rounded-lg border border-red-400/50 bg-red-500/10 px-3 py-2 text-sm text-red-100">
          {error}
        </div>
      )}
    </div>
  );
}

function ModalActions({
  onRematch,
  onMatchmaking,
  onDashboard,
  rematchLabel,
  rematchDisabled,
  matchmakingLabel,
  matchmakingDisabled,
  actionMessage,
  actionError,
  rematchClassName,
  dashboardClassName,
}: Pick<
  ModalProps,
  | "onRematch"
  | "onMatchmaking"
  | "onDashboard"
  | "rematchLabel"
  | "rematchDisabled"
  | "matchmakingLabel"
  | "matchmakingDisabled"
  | "actionMessage"
  | "actionError"
> & {
  rematchClassName?: string;
  dashboardClassName?: string;
}) {
  return (
    <div className="relative z-10">
      <ActionStatus message={actionMessage} error={actionError} />
      <div className="space-y-3">
        <Button variant="app" className={`w-full ${rematchClassName ?? ""}`} onClick={onRematch} disabled={rematchDisabled}>
          {rematchLabel}
        </Button>
        <Button variant="appSecondary" className="w-full" onClick={onMatchmaking} disabled={matchmakingDisabled}>
          {matchmakingLabel}
        </Button>
        <Button variant="appOutline" className={`w-full ${dashboardClassName ?? ""}`} onClick={onDashboard}>
          Retour au Dashboard
        </Button>
      </div>
    </div>
  );
}

const modalCardClass =
  "bg-gradient-to-br from-[#0b0b0c] via-[#151515] to-violet-950/80 border border-violet-300/25 rounded-2xl p-8 max-w-md w-full text-center relative overflow-hidden shadow-2xl shadow-black/50";

const closeButtonClass =
  "absolute top-4 right-4 p-2 rounded-lg border border-white/10 bg-white/5 text-neutral-400 transition-colors hover:bg-white/10 hover:text-white z-10";

const rewardPanelClass =
  "bg-black/45 backdrop-blur rounded-xl p-8 mb-6 border border-white/10 shadow-inner shadow-black/30";

export function VictoryModal({
  isOpen,
  onClose,
  onRematch,
  onMatchmaking,
  onDashboard,
  amount,
  rematchLabel = "Demander une revanche",
  rematchDisabled = false,
  matchmakingLabel = "Nouveau matchmaking",
  matchmakingDisabled = false,
  actionMessage = null,
  actionError = null,
}: ModalProps) {
  useEffect(() => {
    if (isOpen) {
      confetti({
        particleCount: 100,
        spread: 70,
        origin: { y: 0.6 },
        colors: ["#F59E0B", "#FBBF24", "#FCD34D"],
      });
    }
  }, [isOpen]);

  return (
    <AnimatePresence>
      {isOpen && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 p-4"
        >
          <motion.div
            initial={{ scale: 0.8, y: 20 }}
            animate={{ scale: 1, y: 0 }}
            exit={{ scale: 0.8, y: 20 }}
            className={modalCardClass}
          >
            <div className="pointer-events-none absolute inset-0 bg-gradient-to-br from-yellow-300/12 via-violet-500/8 to-transparent" />

            <button
              onClick={onClose}
              className={closeButtonClass}
            >
              <X className="w-5 h-5" />
            </button>

            <motion.div
              initial={{ scale: 0 }}
              animate={{ scale: 1 }}
              transition={{ delay: 0.2, type: "spring", stiffness: 200 }}
              className="relative w-24 h-24 bg-gradient-to-br from-yellow-300 to-yellow-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-2xl shadow-yellow-500/30"
            >
              <Trophy className="w-12 h-12 text-black" />
            </motion.div>

            <motion.h2
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.3 }}
              className="text-5xl font-bold text-white mb-3"
            >
              Victoire !
            </motion.h2>

            <motion.p
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ delay: 0.4 }}
              className="text-neutral-300 mb-6 text-lg"
            >
              Félicitations, vous avez gagné !
            </motion.p>

            <motion.div
              initial={{ scale: 0.8, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              transition={{ delay: 0.5 }}
              className={rewardPanelClass}
            >
              <div className="text-6xl font-bold bg-gradient-to-r from-yellow-200 via-yellow-300 to-violet-200 bg-clip-text text-transparent mb-2">
                +{amount} credits
              </div>
              <div className="text-neutral-400">Ajoute a votre solde</div>
            </motion.div>

            <ModalActions
              onRematch={onRematch}
              onMatchmaking={onMatchmaking}
              onDashboard={onDashboard}
              rematchLabel={rematchLabel}
              rematchDisabled={rematchDisabled}
              matchmakingLabel={matchmakingLabel}
              matchmakingDisabled={matchmakingDisabled}
              actionMessage={actionMessage}
              actionError={actionError}
            />
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}

export function DefeatModal({
  isOpen,
  onClose,
  onRematch,
  onMatchmaking,
  onDashboard,
  amount,
  rematchLabel = "Prendre sa revanche",
  rematchDisabled = false,
  matchmakingLabel = "Nouveau matchmaking",
  matchmakingDisabled = false,
  actionMessage = null,
  actionError = null,
}: ModalProps) {
  return (
    <AnimatePresence>
      {isOpen && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 p-4"
        >
          <motion.div
            initial={{ scale: 0.8, y: 20 }}
            animate={{ scale: 1, y: 0 }}
            exit={{ scale: 0.8, y: 20 }}
            className={modalCardClass}
          >
            <div className="pointer-events-none absolute inset-0 bg-gradient-to-br from-red-500/12 via-violet-500/8 to-transparent" />

            <button
              onClick={onClose}
              className={closeButtonClass}
            >
              <X className="w-5 h-5" />
            </button>

            <motion.div
              initial={{ scale: 0 }}
              animate={{ scale: 1 }}
              transition={{ delay: 0.2, type: "spring" }}
              className="relative w-24 h-24 bg-gradient-to-br from-red-500 to-rose-700 rounded-full flex items-center justify-center mx-auto mb-6 shadow-2xl shadow-red-500/30"
            >
              <X className="w-12 h-12 text-white" />
            </motion.div>

            <h2 className="text-5xl font-bold text-white mb-3">Défaite</h2>
            <p className="text-neutral-300 mb-6 text-lg">
              Votre adversaire a gagné cette partie
            </p>

            <div className={rewardPanelClass}>
              <div className="text-6xl font-bold text-red-400 mb-2">-{amount} credits</div>
              <div className="text-neutral-400">Deduit de votre solde</div>
            </div>

            <ModalActions
              onRematch={onRematch}
              onMatchmaking={onMatchmaking}
              onDashboard={onDashboard}
              rematchLabel={rematchLabel}
              rematchDisabled={rematchDisabled}
              matchmakingLabel={matchmakingLabel}
              matchmakingDisabled={matchmakingDisabled}
              actionMessage={actionMessage}
              actionError={actionError}
            />
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}

export function KoratModal({
  isOpen,
  onClose,
  onRematch,
  onMatchmaking,
  onDashboard,
  amount,
  rematchLabel = "Demander une revanche",
  rematchDisabled = false,
  matchmakingLabel = "Nouveau matchmaking",
  matchmakingDisabled = false,
  actionMessage = null,
  actionError = null,
}: ModalProps) {
  useEffect(() => {
    if (isOpen) {
      // Triple confetti for Korat!
      const duration = 3000;
      const end = Date.now() + duration;

      const frame = () => {
        confetti({
          particleCount: 7,
          angle: 60,
          spread: 55,
          origin: { x: 0 },
          colors: ["#F59E0B", "#FBBF24", "#FCD34D", "#DC2626"],
        });
        confetti({
          particleCount: 7,
          angle: 120,
          spread: 55,
          origin: { x: 1 },
          colors: ["#F59E0B", "#FBBF24", "#FCD34D", "#DC2626"],
        });

        if (Date.now() < end) {
          requestAnimationFrame(frame);
        }
      };
      frame();
    }
  }, [isOpen]);

  return (
    <AnimatePresence>
      {isOpen && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 bg-black/90 backdrop-blur-sm flex items-center justify-center z-50 p-4"
        >
          <motion.div
            initial={{ scale: 0.8, y: 20, rotate: -5 }}
            animate={{ scale: 1, y: 0, rotate: 0 }}
            exit={{ scale: 0.8, y: 20 }}
            className={`${modalCardClass} border-yellow-300/45`}
          >
            {/* Animated glow */}
            <motion.div
              animate={{
                opacity: [0.3, 0.6, 0.3],
                scale: [1, 1.05, 1],
              }}
              transition={{ duration: 2, repeat: Infinity }}
              className="pointer-events-none absolute inset-0 bg-gradient-to-br from-yellow-300/18 via-violet-500/10 to-red-500/14"
            />

            <button
              onClick={onClose}
              className={closeButtonClass}
            >
              <X className="w-5 h-5" />
            </button>

            <motion.div
              animate={{
                rotate: [0, 360],
                scale: [1, 1.1, 1],
              }}
              transition={{ duration: 3, repeat: Infinity, ease: "linear" }}
              className="relative w-28 h-28 bg-gradient-to-br from-yellow-200 via-yellow-400 to-violet-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-2xl shadow-yellow-500/30"
            >
              <Sparkles className="w-14 h-14 text-white" />
            </motion.div>

            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.3 }}
            >
              <div className="inline-block rounded-full border border-yellow-200/50 bg-yellow-300 px-6 py-2 text-xl font-bold text-black shadow-lg shadow-yellow-950/20 mb-4">
                🎯 KORAT !
              </div>
              <h2 className="text-5xl font-bold text-white mb-3">Victoire Légendaire</h2>
              <p className="text-yellow-100 mb-6 text-lg font-medium">
                Dernier pli avec un 3 non battu !<br />
                <span className="text-yellow-300">Gain doublé</span>
              </p>
            </motion.div>

            <motion.div
              initial={{ scale: 0.8, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              transition={{ delay: 0.5 }}
              className={`${rewardPanelClass} border-yellow-300/40`}
            >
              <div className="text-7xl font-bold bg-gradient-to-r from-yellow-100 via-yellow-300 to-violet-200 bg-clip-text text-transparent mb-2">
                +{amount * 2} credits
              </div>
              <div className="text-yellow-200 font-semibold">Gain doublé ! 🔥</div>
            </motion.div>

            <ModalActions
              onRematch={onRematch}
              onMatchmaking={onMatchmaking}
              onDashboard={onDashboard}
              rematchLabel={rematchLabel}
              rematchDisabled={rematchDisabled}
              matchmakingLabel={matchmakingLabel}
              matchmakingDisabled={matchmakingDisabled}
              actionMessage={actionMessage}
              actionError={actionError}
            />
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}

export function ThreeSevenModal({
  isOpen,
  onClose,
  onRematch,
  onMatchmaking,
  onDashboard,
  amount,
  rematchLabel = "Demander une revanche",
  rematchDisabled = false,
  matchmakingLabel = "Nouveau matchmaking",
  matchmakingDisabled = false,
  actionMessage = null,
  actionError = null,
}: ModalProps) {
  useEffect(() => {
    if (isOpen) {
      confetti({
        particleCount: 150,
        spread: 100,
        origin: { y: 0.6 },
        colors: ["#8B5CF6", "#A78BFA", "#C4B5FD"],
      });
    }
  }, [isOpen]);

  return (
    <AnimatePresence>
      {isOpen && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 p-4"
        >
          <motion.div
            initial={{ scale: 0.8, y: 20 }}
            animate={{ scale: 1, y: 0 }}
            exit={{ scale: 0.8, y: 20 }}
            className={modalCardClass}
          >
            <motion.div
              animate={{ opacity: [0.2, 0.4, 0.2] }}
              transition={{ duration: 2, repeat: Infinity }}
              className="pointer-events-none absolute inset-0 bg-gradient-to-br from-violet-500/18 via-yellow-300/6 to-transparent"
            />

            <button
              onClick={onClose}
              className={closeButtonClass}
            >
              <X className="w-5 h-5" />
            </button>

            <motion.div
              initial={{ scale: 0 }}
              animate={{ scale: 1 }}
              transition={{ delay: 0.2, type: "spring" }}
              className="relative w-24 h-24 bg-gradient-to-br from-violet-500 to-purple-700 rounded-full flex items-center justify-center mx-auto mb-6 shadow-2xl shadow-violet-500/35"
            >
              <div className="text-4xl font-bold text-white">777</div>
            </motion.div>

            <div className="inline-block rounded-full border border-violet-200/40 bg-violet-500/20 px-6 py-2 text-lg font-bold text-violet-100 mb-4">
              🎰 THREE 7
            </div>
            <h2 className="text-5xl font-bold text-white mb-3">Victoire Instantanée !</h2>
            <p className="text-violet-100 mb-6 text-lg">
              Trois cartes de valeur 7 !
            </p>

            <div className={rewardPanelClass}>
              <div className="text-6xl font-bold bg-gradient-to-r from-violet-200 via-yellow-200 to-violet-400 bg-clip-text text-transparent mb-2">
                +{amount} credits
              </div>
              <div className="text-violet-200">Victoire instantanée</div>
            </div>

            <ModalActions
              onRematch={onRematch}
              onMatchmaking={onMatchmaking}
              onDashboard={onDashboard}
              rematchLabel={rematchLabel}
              rematchDisabled={rematchDisabled}
              matchmakingLabel={matchmakingLabel}
              matchmakingDisabled={matchmakingDisabled}
              actionMessage={actionMessage}
              actionError={actionError}
            />
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}

export function Under21Modal({
  isOpen,
  onClose,
  onRematch,
  onMatchmaking,
  onDashboard,
  amount,
  rematchLabel = "Demander une revanche",
  rematchDisabled = false,
  matchmakingLabel = "Nouveau matchmaking",
  matchmakingDisabled = false,
  actionMessage = null,
  actionError = null,
}: ModalProps) {
  useEffect(() => {
    if (isOpen) {
      confetti({
        particleCount: 120,
        spread: 80,
        origin: { y: 0.6 },
        colors: ["#10B981", "#34D399", "#6EE7B7"],
      });
    }
  }, [isOpen]);

  return (
    <AnimatePresence>
      {isOpen && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 p-4"
        >
          <motion.div
            initial={{ scale: 0.8, y: 20 }}
            animate={{ scale: 1, y: 0 }}
            exit={{ scale: 0.8, y: 20 }}
            className="bg-gradient-to-br from-emerald-900 via-slate-900 to-emerald-900 border-2 border-emerald-500 rounded-2xl p-8 max-w-md w-full text-center relative overflow-hidden"
          >
            <motion.div
              animate={{ opacity: [0.2, 0.4, 0.2] }}
              transition={{ duration: 2, repeat: Infinity }}
              className="pointer-events-none absolute inset-0 bg-gradient-to-br from-emerald-500/20 to-transparent"
            />

            <button
              onClick={onClose}
              className="absolute top-4 right-4 p-2 hover:bg-slate-800 rounded-lg transition-colors z-10"
            >
              <X className="w-5 h-5 text-slate-400" />
            </button>

            <motion.div
              initial={{ scale: 0 }}
              animate={{ scale: 1 }}
              transition={{ delay: 0.2, type: "spring" }}
              className="relative w-24 h-24 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-full flex items-center justify-center mx-auto mb-6 shadow-2xl shadow-emerald-500/50"
            >
              <Target className="w-12 h-12 text-white" />
            </motion.div>

            <div className="inline-block bg-emerald-500 text-white px-6 py-2 rounded-full font-bold text-lg mb-4">
              🎯 SOUS 21
            </div>
            <h2 className="text-5xl font-bold text-white mb-3">Victoire Instantanée !</h2>
            <p className="text-emerald-200 mb-6 text-lg">
              Somme des cartes ≤ 21 !
            </p>

            <div className="bg-slate-800/80 backdrop-blur rounded-xl p-8 mb-6 border border-emerald-500/30">
              <div className="text-6xl font-bold bg-gradient-to-r from-emerald-400 to-emerald-600 bg-clip-text text-transparent mb-2">
                +{amount} credits
              </div>
              <div className="text-emerald-300">Victoire instantanée</div>
            </div>

            <ModalActions
              onRematch={onRematch}
              onMatchmaking={onMatchmaking}
              onDashboard={onDashboard}
              rematchLabel={rematchLabel}
              rematchDisabled={rematchDisabled}
              matchmakingLabel={matchmakingLabel}
              matchmakingDisabled={matchmakingDisabled}
              actionMessage={actionMessage}
              actionError={actionError}
              rematchClassName="bg-emerald-600 hover:bg-emerald-700"
              dashboardClassName="border-emerald-500 text-emerald-400 hover:bg-emerald-500 hover:text-white"
            />
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
