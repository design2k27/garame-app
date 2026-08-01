import { createBrowserRouter } from "react-router";
import { LandingPage } from "./pages/LandingPage";
import { AuthPage } from "./pages/AuthPage";
import { Dashboard } from "./pages/Dashboard";
import { Wallet } from "./pages/Wallet";
import { Lobby } from "./pages/Lobby";
import { GameRoom } from "./pages/GameRoom";
import { ProtectedRoute } from "./auth/ProtectedRoute";

export const router = createBrowserRouter([
  {
    path: "/",
    Component: LandingPage,
  },
  {
    path: "/auth",
    Component: AuthPage,
  },
  {
    Component: ProtectedRoute,
    children: [
      {
        path: "/dashboard",
        Component: Dashboard,
      },
      {
        path: "/wallet",
        Component: Wallet,
      },
      {
        path: "/lobby",
        Component: Lobby,
      },
      {
        path: "/game/:gameId",
        Component: GameRoom,
      },
    ],
  },
]);
