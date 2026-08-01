import type { CardValue, Suit } from "../components/PlayingCard";
import type { ApiCard, ApiSuit } from "../api/types";

const apiToUiSuit: Record<ApiSuit, Suit> = {
  H: "hearts",
  D: "diamonds",
  C: "clubs",
  S: "spades",
};

const uiToApiSuit: Record<Suit, ApiSuit> = {
  hearts: "H",
  diamonds: "D",
  clubs: "C",
  spades: "S",
};

export function toUiCard(card: ApiCard): { value: CardValue; suit: Suit } {
  return {
    value: card.value as CardValue,
    suit: apiToUiSuit[card.suit],
  };
}

export function toApiSuit(suit: Suit): ApiSuit {
  return uiToApiSuit[suit];
}

export function getWinTypeLabel(winType: string | null | undefined) {
  switch (winType) {
    case "korat":
      return "Korat";
    case "three_seven":
      return "Three 7";
    case "moins_21":
      return "Moins de 21";
    case "match_simple":
      return "Match simple";
    default:
      return "Partie";
  }
}
