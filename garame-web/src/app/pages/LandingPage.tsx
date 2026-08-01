import { useNavigate } from "react-router";
import { PlayingCard } from "../components/PlayingCard";
import { Button } from "../components/Button";
import { motion } from "motion/react";
import { Shield, Trophy, Wallet, Zap } from "lucide-react";

export function LandingPage() {
  const navigate = useNavigate();

  return (
    <div className="app-page">
      {/* Hero Section */}
      <div className="relative min-h-screen flex flex-col items-center justify-center px-4 overflow-hidden">
        {/* Animated background cards */}
        <div className="absolute inset-0 opacity-10">
          <motion.div
            animate={{ rotate: 360 }}
            transition={{ duration: 60, repeat: Infinity, ease: "linear" }}
            className="absolute top-20 left-20"
          >
            <PlayingCard suit="hearts" value={7} size="lg" />
          </motion.div>
          <motion.div
            animate={{ rotate: -360 }}
            transition={{ duration: 50, repeat: Infinity, ease: "linear" }}
            className="absolute bottom-20 right-20"
          >
            <PlayingCard suit="spades" value={8} size="lg" />
          </motion.div>
        </div>

        {/* Content */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.8 }}
          className="relative z-10 text-center max-w-4xl"
        >
          <h1 className="app-brand-text text-7xl font-bold mb-6">
            GARAME
          </h1>
          <p className="text-2xl text-slate-300 mb-12">
            Le jeu de cartes compétitif premium. Mise, stratégie, victoire.
          </p>

          {/* Sample Cards Display */}
          <div className="flex justify-center gap-4 mb-12">
            <motion.div
              initial={{ opacity: 0, x: -50 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: 0.2 }}
            >
              <PlayingCard suit="hearts" value={3} />
            </motion.div>
            <motion.div
              initial={{ opacity: 0, y: -50 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.3 }}
            >
              <PlayingCard suit="spades" value={5} />
            </motion.div>
            <motion.div
              initial={{ opacity: 0, y: 50 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.4 }}
            >
              <PlayingCard suit="diamonds" value={7} />
            </motion.div>
            <motion.div
              initial={{ opacity: 0, x: 50 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: 0.5 }}
            >
              <PlayingCard suit="clubs" value={6} />
            </motion.div>
          </div>

          <Button variant="app" size="lg" onClick={() => navigate("/auth")} className="px-12">
            Jouer Maintenant
          </Button>
        </motion.div>
      </div>

      {/* Features Section */}
      <div className="py-24 px-4 bg-black/25">
        <div className="max-w-6xl mx-auto">
          <h2 className="text-4xl font-bold text-center mb-16 text-white">
            Pourquoi GARAME ?
          </h2>
          <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
            <FeatureCard
              icon={<Zap className="w-8 h-8" />}
              title="Parties Rapides"
              description="Des matchs de 5 minutes. Intensité maximale."
            />
            <FeatureCard
              icon={<Trophy className="w-8 h-8" />}
              title="Compétitif"
              description="Classement mondial. Montez les rangs."
            />
            <FeatureCard
              icon={<Wallet className="w-8 h-8" />}
              title="Credits intégrés"
              description="Suivez votre solde et votre progression."
            />
            <FeatureCard
              icon={<Shield className="w-8 h-8" />}
              title="Sécurisé"
              description="Parties synchronisées. Fair-play garanti."
            />
          </div>
        </div>
      </div>

      {/* Rules Section */}
      <div className="py-24 px-4">
        <div className="max-w-4xl mx-auto">
          <h2 className="text-4xl font-bold text-center mb-12 text-white">
            Règles du Jeu
          </h2>
          <div className="app-card p-8 space-y-6 text-slate-300">
            <RuleItem
              number="1"
              text="2 joueurs, 5 cartes chacun. Paquet de 23 cartes (3-8, sans le 8♠)"
            />
            <RuleItem
              number="2"
              text="Suivez la couleur demandée si possible, sinon jouez n'importe quelle carte"
            />
            <RuleItem
              number="3"
              text="Le gagnant du 5e pli remporte la partie"
            />
            <RuleItem
              number="4"
              text="Victoire immédiate : somme ≤ 21 ou trois cartes de valeur 7"
            />
            <RuleItem
              number="5"
              text="Korat : dernier pli commencé avec un 3 non battu = gain doublé"
            />
          </div>
        </div>
      </div>
    </div>
  );
}

function FeatureCard({
  icon,
  title,
  description,
}: {
  icon: React.ReactNode;
  title: string;
  description: string;
}) {
  return (
    <motion.div
      whileHover={{ y: -8 }}
      className="bg-[#151515]/88 backdrop-blur p-6 rounded-xl border border-white/10"
    >
      <div className="text-violet-300 mb-4">{icon}</div>
      <h3 className="text-xl font-bold text-white mb-2">{title}</h3>
      <p className="text-slate-400">{description}</p>
    </motion.div>
  );
}

function RuleItem({ number, text }: { number: string; text: string }) {
  return (
    <div className="flex gap-4">
      <div className="flex-shrink-0 w-8 h-8 rounded-full bg-gradient-to-br from-violet-500 to-purple-700 flex items-center justify-center font-bold text-white">
        {number}
      </div>
      <p className="pt-1">{text}</p>
    </div>
  );
}
