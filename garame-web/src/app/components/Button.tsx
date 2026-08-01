import { motion, type HTMLMotionProps } from "motion/react";
import type { ReactNode } from "react";

interface ButtonProps extends Omit<HTMLMotionProps<"button">, "children"> {
  children: ReactNode;
  variant?: "primary" | "secondary" | "outline" | "danger" | "app" | "appSecondary" | "appOutline";
  size?: "sm" | "md" | "lg";
}

export function Button({
  children,
  onClick,
  variant = "primary",
  size = "md",
  disabled = false,
  className = "",
  type = "button",
  ...props
}: ButtonProps) {
  const variants = {
    primary: "bg-gradient-to-r from-amber-500 to-amber-600 text-white hover:from-amber-600 hover:to-amber-700",
    secondary: "bg-slate-700 text-white hover:bg-slate-600",
    outline: "border-2 border-amber-500 text-amber-500 hover:bg-amber-500 hover:text-white",
    danger: "bg-red-600 text-white hover:bg-red-700",
    app: "bg-gradient-to-r from-violet-500 to-purple-700 text-white shadow-lg shadow-violet-950/40 ring-1 ring-violet-300/30 hover:from-violet-400 hover:to-purple-600",
    appSecondary: "border border-white/10 bg-white text-violet-900 shadow-lg shadow-black/20 hover:bg-violet-50",
    appOutline: "border border-violet-300/35 bg-violet-500/10 text-violet-100 hover:bg-violet-500/18 hover:border-violet-200/50",
  };

  const sizes = {
    sm: "px-4 py-2 text-sm",
    md: "px-6 py-3 text-base",
    lg: "px-8 py-4 text-lg",
  };

  return (
    <motion.button
      whileHover={{ scale: disabled ? 1 : 1.02 }}
      whileTap={{ scale: disabled ? 1 : 0.98 }}
      onClick={onClick}
      disabled={disabled}
      type={type}
      {...props}
      className={`
        ${variants[variant]}
        ${sizes[size]}
        rounded-lg font-semibold
        transition-all duration-200
        disabled:opacity-50 disabled:cursor-not-allowed
        ${className}
      `}
    >
      {children}
    </motion.button>
  );
}
