import { useEffect } from "react";
import { Outlet, useLocation, Link, useNavigate } from "react-router-dom";
import { TopNav } from "./TopNav";
import { ChatbotWidget } from "@/components/chatbot/ChatbotWidget";
import { ChevronRight, Home, ExternalLink } from "lucide-react";

const labelFor = (path: string) => {
  if (path.includes("disease")) return "Disease Explorer";
  if (path.includes("compound")) return "Compound Explorer";
  if (path.includes("integrated")) return "Integrated Explorer";
  return null;
};

export const AppLayout = () => {
  const location = useLocation();
  const navigate = useNavigate();
  const explorerLabel = labelFor(location.pathname);
  const isGraph = location.pathname.includes("/graph");

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Secret Developer Easter Egg Shortcut: 
      // 1. Shift + Alt + D (Highly safe on Windows OS, ignores graphics overrides)
      // 2. Ctrl + Alt + D (Standard fallback)
      const isDKey = e.key.toLowerCase() === "d" || e.code === "KeyD";
      const isShiftAlt = e.shiftKey && e.altKey && !e.ctrlKey;
      const isCtrlAlt = e.ctrlKey && e.altKey;

      if (isDKey && (isShiftAlt || isCtrlAlt)) {
        e.preventDefault();
        navigate("/api-tester");
      }
    };
    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [navigate]);

  return (
    <div className="kg-app-layout min-h-screen flex flex-col bg-gradient-warm">
      <TopNav />

      {explorerLabel && (
        <div className="border-b border-border/60 bg-background/60">
          <div className="container py-3 flex items-center gap-1.5 text-xs text-muted-foreground">
            <Link to="/" className="inline-flex items-center gap-1 hover:text-foreground transition-smooth">
              <Home className="h-3.5 w-3.5" />
              Modes
            </Link>
            <ChevronRight className="h-3.5 w-3.5 opacity-50" />
            <span className="text-foreground font-medium">{explorerLabel}</span>
          </div>
        </div>
      )}

      <main className="flex-1">
        <div key={location.pathname} className="animate-fade-in">
          <Outlet />
        </div>
      </main>

      <footer className="border-t border-border/60 bg-background/40">
        <div className="container py-12">
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-12">
            <div className="space-y-4 col-span-2 lg:col-span-1">
              <div className="space-y-3">
                <h3 className="font-display font-bold text-foreground">About ShodhanaKG</h3>
                <p className="text-sm text-muted-foreground leading-relaxed">
                  An Integrative Ayurvedic Purification Knowledge Graph bridging traditional wisdom with modern research.
                </p>
              </div>
            </div>
            <div className="space-y-3">
              <h3 className="font-display font-bold text-foreground">Resources</h3>
              <nav className="flex flex-col gap-2 text-sm text-muted-foreground">
                <Link to="/how-to-use" className="hover:text-primary transition-smooth">How to use</Link>
                <Link to="/faq" className="hover:text-primary transition-smooth">FAQ</Link>
              </nav>
            </div>
            <div className="space-y-3">
              <h3 className="font-display font-bold text-foreground">Other Databases</h3>
              <nav className="flex flex-col gap-2 text-sm text-muted-foreground">
                <a href="https://webs.iiitd.edu.in/raghava/plantdrppred/" target="_blank" rel="noopener noreferrer" className="hover:text-primary transition-smooth flex items-center gap-1.5">
                  PlantDRPpred <ExternalLink className="h-3 w-3 opacity-50" />
                </a>
                <a href="https://webs.iiitd.edu.in/raghava/coregmc/" target="_blank" rel="noopener noreferrer" className="hover:text-primary transition-smooth flex items-center gap-1.5">
                  CoReGMC <ExternalLink className="h-3 w-3 opacity-50" />
                </a>
                <a href="https://webs.iiitd.edu.in/raghava/npact/" target="_blank" rel="noopener noreferrer" className="hover:text-primary transition-smooth flex items-center gap-1.5">
                  NPACT <ExternalLink className="h-3 w-3 opacity-50" />
                </a>
              </nav>
            </div>
            <div className="space-y-3">
              <h3 className="font-display font-bold text-foreground">Connect</h3>
              <nav className="flex flex-col gap-2 text-sm text-muted-foreground">
                <Link to="/contact" className="hover:text-primary transition-smooth">Contact Us</Link>
              </nav>
            </div>
          </div>
          <div className="mt-12 pt-6 border-t border-border/40 text-xs text-muted-foreground flex justify-center items-center">
            <span>© {new Date().getFullYear()} ShodhanaKG · All rights reserved.</span>
          </div>
        </div>
      </footer>

      {import.meta.env.VITE_CHAT_ENABLED === 'true' && <ChatbotWidget />}
    </div>
  );
};
