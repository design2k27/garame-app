import { User } from "lucide-react";

interface PlayerAvatarProps {
  name: string;
  balance?: number;
  isOnline?: boolean;
  size?: "sm" | "md" | "lg";
  showBalance?: boolean;
}

export function PlayerAvatar({
  name,
  balance,
  isOnline = true,
  size = "md",
  showBalance = false,
}: PlayerAvatarProps) {
  const sizes = {
    sm: { container: "w-8 h-8", text: "text-xs", icon: "w-4 h-4" },
    md: { container: "w-10 h-10", text: "text-sm", icon: "w-5 h-5" },
    lg: { container: "w-12 h-12", text: "text-base", icon: "w-6 h-6" },
  };

  const currentSize = sizes[size];
  const initials = name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);

  return (
    <div className="flex items-center gap-3">
      <div className="relative">
        <div
          className={`
          ${currentSize.container}
          bg-gradient-to-br from-amber-500 to-amber-600
          rounded-full flex items-center justify-center
          text-white font-bold ${currentSize.text}
          shadow-lg
        `}
        >
          {initials}
        </div>
        {isOnline && (
          <div className="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-500 border-2 border-slate-900 rounded-full" />
        )}
      </div>
      <div>
        <div className="text-white font-semibold">{name}</div>
        {showBalance && balance !== undefined && (
          <div className="text-sm text-slate-400">{balance} credits</div>
        )}
      </div>
    </div>
  );
}
