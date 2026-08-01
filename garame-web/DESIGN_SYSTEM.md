# GARAME - Design System Documentation

## 🎨 Vision
Application de jeu de cartes premium dark-mode inspirée du poker online, eSport et fintech.

---

## 🎯 Principes de Design

### 1. **Premium First**
- Surfaces avec backdrop-blur
- Shadows prononcées et glows
- Gradients subtils
- Animations fluides

### 2. **Lisibilité Maximale**
- Contraste fort dark mode
- Typographie claire et hiérarchisée
- Cartes authentiques type casino
- Symboles de cartes français classiques

### 3. **Gaming UX**
- Feedback immédiat
- Animations micro-interactions
- États visuels clairs (hover, selected, disabled)
- Modals célébratoires avec confetti

---

## 🎨 Palette de Couleurs

### Background & Surfaces
```css
--background-primary: from-slate-950 via-slate-900 to-slate-950
--surface-primary: bg-slate-800/50 backdrop-blur
--surface-secondary: bg-slate-900/50
--border-primary: border-slate-700
--border-accent: border-amber-500
```

### Accent Colors
```css
--amber-primary: from-amber-500 to-amber-600
--amber-glow: shadow-amber-500/50
--green-felt: from-green-900/30 via-green-800/20 to-green-900/30
```

### Semantic Colors
```css
--success: emerald-500 (Victoire, Under21)
--warning: amber-500 (Actions principales)
--danger: red-500 (Défaite, actions destructives)
--special: purple-500 (Three 7)
--legendary: amber + red gradient (Korat)
```

### Card Colors
```css
--card-red: #DC2626 (♥ Cœur, ♦ Carreau)
--card-black: #1E293B (♣ Trèfle, ♠ Pique)
--card-bg: white
--card-border: gray-300
```

---

## 📐 Spacing & Layout

### Container Max-Width
- Small screens: 100%
- Desktop content: max-w-4xl, max-w-6xl, max-w-7xl

### Padding Scale
```css
p-2   /* 8px - tight */
p-4   /* 16px - standard */
p-6   /* 24px - comfortable */
p-8   /* 32px - spacious */
```

### Gap Scale
```css
gap-2 /* 8px */
gap-3 /* 12px */
gap-4 /* 16px */
gap-6 /* 24px */
```

---

## 🔤 Typographie

### Headers
- H1: `text-7xl font-bold` - Landing hero
- H2: `text-4xl font-bold` - Section titles
- H3: `text-2xl font-bold` - Card titles
- H4: `text-xl font-bold` - Subsections

### Body Text
- Large: `text-lg` - Descriptions importantes
- Base: `text-base` - Contenu standard
- Small: `text-sm` - Labels, metadata
- Tiny: `text-xs` - Badges, hints

### Font Weights
- Bold: `font-bold` - Headers, montants
- Semibold: `font-semibold` - Boutons, labels importants
- Medium: `font-medium` - Texte standard
- Normal: `font-normal` - Body text

---

## 🃏 Composants Clés

### PlayingCard
**Tailles:**
- `sm`: 14x20 (w-14 h-20) - Aperçus
- `md`: 20x32 (w-20 h-32) - Standard gameplay
- `lg`: 24x36 (w-24 h-36) - Hero, center trick

**États:**
- `isPlayable`: Cursor pointer, hover lift, couleurs vives
- `!isPlayable`: Opacity 30%, grayscale, disabled cursor
- `isSelected`: Ring amber-400, translate-y-3, shadow glow
- `faceDown`: Gradient red, pattern dos de carte

**Symboles:**
- ♥ Cœur (hearts) - Rouge #DC2626
- ♦ Carreau (diamonds) - Rouge #DC2626
- ♣ Trèfle (clubs) - Noir #1E293B
- ♠ Pique (spades) - Noir #1E293B

### Button
**Variants:**
- `primary`: Amber gradient, white text
- `secondary`: Slate-700, white text
- `outline`: Border amber, amber text, hover fill
- `danger`: Red-600, white text

**Sizes:**
- `sm`: px-4 py-2 text-sm
- `md`: px-6 py-3 text-base
- `lg`: px-8 py-4 text-lg

**Interactions:**
- Hover: scale 1.02
- Tap: scale 0.98
- Disabled: opacity-50, cursor-not-allowed

### Game Modals
**Types:**
1. **VictoryModal** - Amber, Trophy icon, confetti
2. **DefeatModal** - Red, X icon, sombre
3. **KoratModal** - Amber+Red gradient, Sparkles, triple confetti, glow animation
4. **ThreeSevenModal** - Purple, "777" icon, confetti violet
5. **Under21Modal** - Emerald, Target icon, confetti vert

**Structure commune:**
- Backdrop: bg-black/80 backdrop-blur
- Container: gradient border, rounded-2xl, p-8
- Icon: Circle 24x24, gradient, shadow-glow
- Amount: text-6xl, gradient text
- Actions: 2 buttons (Play Again + Dashboard)

### PlayerAvatar
**Sizes:**
- `sm`: 8x8, text-xs
- `md`: 10x10, text-sm
- `lg`: 12x12, text-base

**Features:**
- Initiales (2 lettres max)
- Gradient amber background
- Online dot (green-500, bottom-right)
- Optional balance display

---

## 🎬 Animations

### Motion Patterns
```jsx
// Hover - Cards
whileHover={{ y: -12, scale: 1.05 }}

// Hover - Buttons
whileHover={{ scale: 1.02 }}

// Tap
whileTap={{ scale: 0.98 }}

// Modal Entry
initial={{ opacity: 0, scale: 0.8, y: 20 }}
animate={{ opacity: 1, scale: 1, y: 0 }}

// Card Deal
initial={{ opacity: 0, y: -20 }}
animate={{ opacity: 1, y: 0 }}
transition={{ delay: i * 0.1 }}

// Glow Pulse (Korat)
animate={{ opacity: [0.3, 0.6, 0.3], scale: [1, 1.05, 1] }}
transition={{ duration: 2, repeat: Infinity }}
```

### Confetti Events
- **Victory**: 100 particles, amber colors
- **Korat**: Continuous 3s, dual-angle, amber+red
- **Three 7**: 150 particles, purple colors
- **Under 21**: 120 particles, emerald colors

---

## 📱 Responsive Breakpoints

### Mobile First
```css
/* Mobile: base styles */
grid-cols-1
text-2xl

/* Tablet: md: */
@media (min-width: 768px)
grid-cols-2
text-4xl

/* Desktop: lg: */
@media (min-width: 1024px)
grid-cols-3, grid-cols-4
text-5xl, text-7xl
```

### Game Table Responsive
- **Desktop**: Horizontal layout, large cards
- **Mobile**: Vertical stack, smaller cards (sm size)

---

## 🎮 Game Table Anatomy

### Layout Zones
```
┌─────────────────────────────┐
│   Opponent Info + Cards     │ Top
├─────────────────────────────┤
│                             │
│     Center Play Area        │ Middle
│    (Trick + Status)         │
│                             │
├─────────────────────────────┤
│   Player Hand + Info        │ Bottom
└─────────────────────────────┘
```

### Background Layers
1. Base gradient: slate-950 → slate-900
2. Green felt radial gradients (3 overlapping)
3. Felt texture (repeating diagonal lines, opacity 5%)

### Center Trick Area
- Min height: 200px
- Flex center alignment
- Cards animate from player area with rotate + scale
- Status card below shows demanded suit symbol

---

## 🔧 Utility Classes Patterns

### Surfaces Premium
```jsx
className="bg-slate-800/50 backdrop-blur border border-slate-700 rounded-xl"
```

### Gradient Text
```jsx
className="bg-gradient-to-r from-amber-400 to-amber-600 bg-clip-text text-transparent"
```

### Shadow Glow
```jsx
className="shadow-2xl shadow-amber-500/50"
```

### Interactive Card
```jsx
className="hover:border-amber-400 hover:shadow-amber-500/50 transition-all"
```

---

## 📊 Data Visualization

### Stats Cards
- Icon in colored background (amber-500/10)
- Large value (text-3xl font-bold)
- Small subtitle with trend color (green/red-400)

### Transaction List
- Icon left (colored by type)
- Description + date
- Amount right (colored by sign)
- Background: slate-900/50 rounded-lg

### Progress Indicators
- Plis counter: "2 - 1" format
- Timer: clock icon + countdown (amber when < 10s)
- Cards remaining: number badge

---

## ✨ Special Effects

### Table Felt
```css
background-image: radial-gradient(circle at 50% 50%, rgba(34, 197, 94, 0.15) 0%, transparent 60%),
                  radial-gradient(circle at 30% 40%, rgba(16, 185, 129, 0.1) 0%, transparent 40%),
                  radial-gradient(circle at 70% 60%, rgba(5, 150, 105, 0.1) 0%, transparent 40%)
```

### Card Shadows (Premium)
```jsx
boxShadow: "0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.2)"
```

### Glossy Card Effect
```jsx
<div className="absolute inset-0 bg-gradient-to-br from-white/40 via-transparent to-transparent rounded-lg pointer-events-none" />
```

---

## 🎯 Accessibility

### Focus States
- Ring: `focus:ring-2 focus:ring-amber-500`
- Outline: `focus:outline-none` (custom ring used)

### Color Contrast
- Text on dark: white, slate-300, slate-400
- Minimum contrast ratio: 4.5:1 (WCAG AA)

### Interactive States
- Disabled: opacity-50, cursor-not-allowed
- Loading: animate-pulse on indicators
- Error: red-500 border, red-400 text

---

## 📦 File Structure

```
src/app/
├── components/
│   ├── PlayingCard.tsx       # Carte premium avec états
│   ├── Button.tsx             # Bouton avec variants
│   ├── GameModals.tsx         # 5 modals de fin de jeu
│   └── PlayerAvatar.tsx       # Avatar joueur
├── pages/
│   ├── LandingPage.tsx        # Hero + Features + Rules
│   ├── AuthPage.tsx           # Login/Register
│   ├── Dashboard.tsx          # Stats + Historique
│   ├── Wallet.tsx             # Recharge/Retrait
│   ├── Lobby.tsx              # Matchmaking
│   └── GameRoom.tsx           # Table de jeu principale
├── routes.tsx                 # React Router config
└── App.tsx                    # Root avec dark mode
```

---

## 🚀 Implementation Guidelines

### 1. Toujours utiliser Motion pour les animations
```jsx
import { motion, AnimatePresence } from "motion/react";
```

### 2. Privilégier backdrop-blur pour les surfaces
```jsx
className="bg-slate-800/50 backdrop-blur"
```

### 3. Utiliser les cartes classiques uniquement
- Valeurs: 3, 4, 5, 6, 7, 8
- Couleurs: ♥♦♣♠
- Pas de cartes fantasy/tarot/illustrées

### 4. Confetti pour les victoires
```jsx
import confetti from "canvas-confetti";
confetti({ particleCount: 100, ... });
```

### 5. Navigation React Router
```jsx
import { useNavigate } from "react-router";
const navigate = useNavigate();
navigate("/dashboard");
```

---

## 🎨 Brand Assets

### Logo Text
"GARAME" - text-7xl, gradient amber

### Tagline
"Le jeu de cartes compétitif premium"

### Color Signature
Amber (#F59E0B) sur Dark Slate (#0F172A)

---

## 📝 Content Guidelines

### Tone of Voice
- Premium mais accessible
- Compétitif mais fair-play
- Technique mais clair
- Français uniquement

### Messaging
- "Mise, stratégie, victoire"
- "Parties rapides, intensité maximale"
- "Classement mondial"
- "Fair-play garanti"

---

**Version**: 1.0  
**Last Updated**: 2026-05-30  
**Author**: GARAME Design Team
