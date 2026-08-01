export type ApiSuit = "H" | "D" | "C" | "S";
export type ApiCardValue = 3 | 4 | 5 | 6 | 7 | 8;
export type GameStatus = "waiting" | "playing" | "finished";
export type WinType = "moins_21" | "three_seven" | "match_simple" | "korat";

export interface ApiUser {
  id: string;
  username: string;
  email: string;
  credits: number;
  gamesPlayed: number;
  gamesWon: number;
  createdAt: string;
}

export interface AuthResponse {
  token: string;
  user: ApiUser;
}

export interface ApiErrorBody {
  error?: {
    code: string;
    message: string;
    details?: Record<string, string>;
  };
}

export interface GameSummaryPlayer {
  id: string;
  username: string;
  credits: number;
  position: number;
}

export interface GameSummary {
  id: string;
  status: GameStatus;
  stateVersion: number;
  currentRound: number;
  currentLeader: string | null;
  players: GameSummaryPlayer[];
  startedAt: string;
  endedAt: string | null;
}

export interface ApiCard {
  value: ApiCardValue;
  suit: ApiSuit;
}

export interface ApiMove {
  player: string;
  card: ApiCard;
  playOrder: number;
}

export interface GameFull {
  id: string;
  status: GameStatus;
  stateVersion: number;
  currentRound: number;
  currentLeader: string | null;
  winType: WinType | null;
  winner: string | null;
  myHand: ApiCard[];
  myTricksWon: number;
  myAckedStateVersion: number;
  opponent: {
    id: string;
    username: string;
    credits: number;
    tricksWon: number;
    cardsLeft: number;
    ackedStateVersion: number;
  } | null;
  currentRoundData: {
    number: number;
    leader: string;
    moves: ApiMove[];
  } | null;
  rounds: Array<{
    number: number;
    leader: string;
    winner: string | null;
    winType: WinType | null;
    moves: ApiMove[];
  }>;
  startedAt: string;
  endedAt: string | null;
}

export interface GameResultPayload {
  status: GameStatus | "waiting" | "round_complete" | "finished";
  winner: string | null;
  winType: WinType | null;
  roundWinner: string | null;
  nextLeader: string | null;
  nextRound: number | null;
}

export interface SingleGameResponse<TGame = GameSummary | GameFull> {
  action: "created" | "queued" | "joined" | "rejoined" | "fetched" | "synced" | "played";
  game: TGame;
  result: GameResultPayload;
}

export interface ListGamesResponse {
  action: "listed";
  games: GameSummary[];
}

export interface PollResponse {
  action: "polled";
  changed: boolean;
  serverVersion: number;
  game: GameFull | null;
  result: GameResultPayload | null;
  pollAfterMs: number;
}

export interface ProfileStatsResponse {
  action: "fetched";
  stats: {
    summary: {
      totalGames: number;
      wins: number;
      losses: number;
      winRate: number;
      credits: number;
      gamesPlayed: number;
      gamesWon: number;
    };
    byWinType: Record<WinType, { wins: number; losses: number; total: number }>;
    streak: { type: "win" | "loss" | "none"; count: number };
    recent: Array<{
      gameId: string;
      resultId: string;
      didWin: boolean;
      winType: WinType;
      stakeMultiplier: number;
      creditsDelta: number;
      opponent: { id: string; username: string };
      playedAt: string;
    }>;
  };
}

export interface HistoryResponse {
  action: "listed";
  items: Array<{
    id: string;
    status: GameStatus;
    startedAt: string;
    endedAt: string | null;
    winType: WinType | null;
    didWin: boolean;
    opponent: { id: string; username: string; credits: number } | null;
    result: {
      winner: string;
      loser: string;
      winType: WinType;
      stakeMultiplier: number;
      createdAt: string;
    } | null;
  }>;
  pagination: {
    page: number;
    perPage: number;
    total: number;
    totalPages: number;
  };
}
