import { motion } from "motion/react";

export type Suit = "hearts" | "diamonds" | "clubs" | "spades";
export type CardValue = 3 | 4 | 5 | 6 | 7 | 8;

interface PlayingCardProps {
  suit: Suit;
  value: CardValue;
  isPlayable?: boolean;
  isSelected?: boolean;
  isHighlighted?: boolean;
  onClick?: () => void;
  className?: string;
  size?: "sm" | "md" | "lg";
  faceDown?: boolean;
  backSkin?: "classic" | "gold";
}

const suitSymbols = {
  hearts: "♥",
  diamonds: "♦",
  clubs: "♣",
  spades: "♠",
};

const suitColors = {
  hearts: "#DC2626",
  diamonds: "#DC2626",
  clubs: "#1E293B",
  spades: "#1E293B",
};

export function PlayingCard({
  suit,
  value,
  isPlayable = true,
  isSelected = false,
  isHighlighted = false,
  onClick,
  className = "",
  size = "md",
  faceDown = false,
  backSkin = "classic",
}: PlayingCardProps) {
  const suitSymbol = suitSymbols[suit];
  const suitColor = suitColors[suit];

  const sizes = {
    sm: { container: "w-14 h-20", corner: "text-sm", symbol: "text-xl", center: "text-3xl" },
    md: { container: "w-20 h-32", corner: "text-lg", symbol: "text-2xl", center: "text-5xl" },
    lg: { container: "w-24 h-36", corner: "text-xl", symbol: "text-3xl", center: "text-6xl" },
  };

  const currentSize = sizes[size];
  const backStyle = {
    classic: {
      surface: "bg-gradient-to-br from-red-800 via-red-900 to-red-950 border-red-700",
      pattern: "border-red-600",
      symbol: "text-red-600",
      glow: "from-red-500/20 via-transparent to-black/20",
    },
    gold: {
      surface: "bg-gradient-to-br from-[#080808] via-violet-950 to-[#151515] border-yellow-300/65",
      pattern: "border-yellow-300/60",
      symbol: "text-yellow-200",
      glow: "from-yellow-300/18 via-violet-400/10 to-black/40",
    },
  }[backSkin];

  if (faceDown) {
    return (
      <motion.div
        whileHover={isPlayable ? { scale: 1.02 } : {}}
        className={`
          ${currentSize.container}
          ${backStyle.surface}
          rounded-lg shadow-2xl relative overflow-hidden
          border-2
          ${isPlayable ? "cursor-pointer" : ""}
          ${className}
        `}
      >
        <div className={`absolute inset-0 bg-gradient-to-br ${backStyle.glow}`} />
        <div className="absolute inset-0 flex items-center justify-center">
          <div className="grid grid-cols-3 grid-rows-3 gap-1 opacity-30">
            {Array.from({ length: 9 }).map((_, i) => (
              <div key={i} className={`w-3 h-3 rounded-full border-2 ${backStyle.pattern}`} />
            ))}
          </div>
        </div>
        <div className="absolute inset-0 flex items-center justify-center">
          <span className={`text-4xl opacity-45 ${backStyle.symbol}`}>♠</span>
        </div>
        {backSkin === "gold" && (
          <div className="absolute inset-2 rounded-md border border-yellow-200/25 shadow-inner shadow-yellow-200/10" />
        )}
      </motion.div>
    );
  }

  return (
    <motion.div
      animate={isHighlighted ? { y: [0, -4, 0] } : { y: 0 }}
      transition={isHighlighted ? { duration: 1.8, repeat: Infinity, ease: "easeInOut" } : undefined}
      whileHover={isPlayable ? { y: -12, scale: 1.05 } : {}}
      whileTap={isPlayable ? { scale: 0.98 } : {}}
      onClick={isPlayable ? onClick : undefined}
      className={`
        ${currentSize.container}
        bg-white rounded-lg shadow-2xl relative overflow-hidden
        border-2
        ${isPlayable ? "cursor-pointer border-gray-300 hover:shadow-yellow-400/45 hover:border-yellow-300 transition-all" : "cursor-not-allowed border-slate-400 opacity-45 grayscale saturate-50"}
        ${isHighlighted ? "ring-2 ring-violet-300/80 shadow-violet-400/40" : ""}
        ${isSelected ? "ring-4 ring-yellow-300 shadow-yellow-400/45 -translate-y-3" : ""}
        ${className}
      `}
      style={{
        boxShadow: isSelected
          ? "0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2), 0 0 0 4px rgb(253 224 71)"
          : isHighlighted
            ? "0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2), 0 0 24px rgba(168, 85, 247, 0.35)"
          : "0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2)"
      }}
    >
      {/* Premium card background texture */}
      <div className="absolute inset-0 bg-gradient-to-br from-white via-gray-50 to-white rounded-lg opacity-60" />

      {/* Top left corner */}
      <div className="absolute top-2 left-2 flex flex-col items-center gap-0 z-10">
        <span
          className={`${currentSize.corner} font-bold leading-none`}
          style={{ color: suitColor }}
        >
          {value}
        </span>
        <span
          className={`${currentSize.symbol} leading-none`}
          style={{ color: suitColor }}
        >
          {suitSymbol}
        </span>
      </div>

      {/* Center symbol - Premium layout */}
      <div className="absolute inset-0 flex items-center justify-center z-10">
        <span
          className={`${currentSize.center} drop-shadow-sm`}
          style={{ color: suitColor }}
        >
          {suitSymbol}
        </span>
      </div>

      {/* Bottom right corner (rotated) */}
      <div className="absolute bottom-2 right-2 flex flex-col items-center gap-0 rotate-180 z-10">
        <span
          className={`${currentSize.corner} font-bold leading-none`}
          style={{ color: suitColor }}
        >
          {value}
        </span>
        <span
          className={`${currentSize.symbol} leading-none`}
          style={{ color: suitColor }}
        >
          {suitSymbol}
        </span>
      </div>

      {/* Glossy effect */}
      <div className="absolute inset-0 bg-gradient-to-br from-white/40 via-transparent to-transparent rounded-lg pointer-events-none" />
      {!isPlayable && (
        <div className="absolute inset-0 z-20 flex items-center justify-center bg-slate-950/20">
          <div className="rounded-full border border-slate-300/80 bg-white/85 px-2 py-1 text-[10px] font-bold uppercase text-slate-700 shadow">
            Bloquee
          </div>
        </div>
      )}
    </motion.div>
  );
}
