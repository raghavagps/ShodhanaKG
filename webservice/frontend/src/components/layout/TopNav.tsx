import { NavLink, Link } from "react-router-dom";
import { Leaf, ChevronDown, Home, Activity, FlaskConical, Network, Share2, HelpCircle, MessageCircle, Eye, Info, BookOpen, Menu, Search } from "lucide-react";
import { cn } from "@/lib/utils";
import { useColorblind } from "@/hooks/useColorblind";

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

export const TopNav = () => {
  const { isColorblind, toggleColorblind } = useColorblind();
  

  return (
    <header className="sticky top-0 z-40 w-full border-b-2 border-herbal/20 bg-secondary/95 backdrop-blur-md shadow-sm">
      <div className="container flex h-16 items-center justify-between gap-4">
        <Link to="/" className="flex items-center gap-2.5 group min-w-0">
          <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-gradient-herbal shadow-sm">
            <Leaf className="h-4.5 w-4.5 text-primary-foreground" strokeWidth={2.2} />
          </span>
          <div className="leading-tight min-w-0">
            <div className="font-display text-lg font-semibold text-bark">
              ShodhanaKG
            </div>
            <div className="text-[10px] uppercase tracking-[0.18em] text-muted-foreground font-medium">
              {"Integrative Ayurvedic purification knowledge graph"}
            </div>
          </div>
        </Link>

        {/* Spacer to push nav to the right */}
        <div className="flex-1" />

        <nav className="hidden xl:flex shrink-0 items-center gap-6 whitespace-nowrap">
          <Link 
            to="/" 
            className="flex items-center gap-1.5 text-[15px] font-semibold text-bark hover:text-herbal transition-smooth"
          >
            <Home className="h-4 w-4" />
            {"Home"}
          </Link>

          <DropdownMenu>
            <DropdownMenuTrigger className="flex items-center gap-1.5 text-[15px] font-semibold text-bark hover:text-herbal outline-none transition-smooth group">
              {"Explorer"}
              <ChevronDown className="h-4 w-4 transition-transform group-data-[state=open]:rotate-180" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48 p-1.5 animate-in fade-in zoom-in-95 duration-200">
              <DropdownMenuItem asChild className="rounded-md">
                <Link to="/explorer/disease" className="flex items-center gap-2 px-2 py-2">
                  <Activity className="h-4 w-4 text-terracotta" />
                  <span className="text-xs font-semibold">{"Disease Explorer"}</span>
                </Link>
              </DropdownMenuItem>
              <DropdownMenuItem asChild className="rounded-md">
                <Link to="/explorer/compound" className="flex items-center gap-2 px-2 py-2">
                  <FlaskConical className="h-4 w-4 text-herbal" />
                  <span className="text-xs font-semibold">{"Compound Explorer"}</span>
                </Link>
              </DropdownMenuItem>
              <DropdownMenuItem asChild className="rounded-md">
                <Link to="/explorer/integrated" className="flex items-center gap-2 px-2 py-2">
                  <Network className="h-4 w-4 text-bark" />
                  <span className="text-xs font-semibold">{"Integrated Explorer"}</span>
                </Link>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <DropdownMenu>
            <DropdownMenuTrigger className="flex items-center gap-1.5 text-[15px] font-semibold text-bark hover:text-herbal outline-none transition-smooth group">
              {"Knowledge Graph"}
              <ChevronDown className="h-4 w-4 transition-transform group-data-[state=open]:rotate-180" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-52 p-1.5 animate-in fade-in zoom-in-95 duration-200">
              <DropdownMenuItem asChild className="rounded-md">
                <Link to="/explorer/disease/graph" className="flex items-center gap-2 px-2 py-2">
                  <Share2 className="h-4 w-4 text-terracotta" />
                  <span className="text-xs font-semibold">{"Disease Graph"}</span>
                </Link>
              </DropdownMenuItem>
              <DropdownMenuItem asChild className="rounded-md">
                <Link to="/explorer/compound/graph" className="flex items-center gap-2 px-2 py-2">
                  <Share2 className="h-4 w-4 text-herbal" />
                  <span className="text-xs font-semibold">{"Compound Graph"}</span>
                </Link>
              </DropdownMenuItem>
              <DropdownMenuItem asChild className="rounded-md">
                <Link to="/explorer/integrated/graph" className="flex items-center gap-2 px-2 py-2">
                  <Share2 className="h-4 w-4 text-bark" />
                  <span className="text-xs font-semibold">{"Integrated Graph"}</span>
                </Link>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <DropdownMenu>
            <DropdownMenuTrigger className="flex items-center gap-1.5 text-[15px] font-semibold text-bark hover:text-herbal outline-none transition-smooth group">
              {"Help"}
              <ChevronDown className="h-4 w-4 transition-transform group-data-[state=open]:rotate-180" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48 p-1.5 animate-in fade-in zoom-in-95 duration-200">
              <DropdownMenuItem asChild className="rounded-md">
                <Link to="/faq" className="flex items-center gap-2 px-2 py-2">
                  <HelpCircle className="h-4 w-4 text-blue-500" />
                  <span className="text-xs font-semibold">{"FAQs"}</span>
                </Link>
              </DropdownMenuItem>
              <DropdownMenuItem asChild className="rounded-md">
                <Link to="/how-to-use" className="flex items-center gap-2 px-2 py-2">
                  <BookOpen className="h-4 w-4 text-orange-500" />
                  <span className="text-xs font-semibold">{"How to Use"}</span>
                </Link>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <DropdownMenu>
            <DropdownMenuTrigger className="flex items-center gap-1.5 text-[15px] font-semibold text-bark hover:text-herbal outline-none transition-smooth group">
              {"About Us"}
              <ChevronDown className="h-4 w-4 transition-transform group-data-[state=open]:rotate-180" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48 p-1.5 animate-in fade-in zoom-in-95 duration-200">
              <DropdownMenuItem asChild className="rounded-md">
                <Link to="/team" className="flex items-center gap-2 px-2 py-2">
                  <Info className="h-4 w-4 text-herbal" />
                  <span className="text-xs font-semibold">{"Developers"}</span>
                </Link>
              </DropdownMenuItem>
              <DropdownMenuItem asChild className="rounded-md">
                <Link to="/contact" className="flex items-center gap-2 px-2 py-2">
                  <MessageCircle className="h-4 w-4 text-blue-500" />
                  <span className="text-xs font-semibold">{"Contact"}</span>
                </Link>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <NavLink to="/advanced-search" className="flex items-center gap-1.5 text-[15px] font-semibold text-bark hover:text-herbal transition-smooth"><Search className="h-4 w-4" />Advanced Search</NavLink>
          {/* Minimalist Capsule Toggle */}
          

          <button
            onClick={toggleColorblind}
            className={cn(
              "flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all shadow-sm shrink-0",
              isColorblind 
                ? "bg-blue-600 text-white hover:bg-blue-700 shadow-blue-200" 
                : "bg-secondary text-muted-foreground hover:text-foreground hover:bg-secondary/80"
            )}
            title={isColorblind ? "Colorblind ON" : "Colorblind Mode"}
          >
            <Eye className="h-3.5 w-3.5" />
            <span className="grid">
              <span aria-hidden="true" className="invisible col-start-1 row-start-1">Colorblind Mode</span>
              <span className="col-start-1 row-start-1">{isColorblind ? "Colorblind ON" : "Colorblind Mode"}</span>
            </span>
          </button>
        </nav>
        <div className="xl:hidden">
          <DropdownMenu><DropdownMenuTrigger aria-label="Open navigation" className="p-2"><Menu className="h-5 w-5" /></DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              {[['Home','/'],['Disease Explorer','/explorer/disease'],['Compound Explorer','/explorer/compound'],['Integrated Explorer','/explorer/integrated'],['How to Use','/how-to-use'],['FAQs','/faq'],['About Us','/team'],['Advanced Search','/advanced-search']].map(([label,url])=><DropdownMenuItem asChild key={url}><NavLink to={url}>{label}</NavLink></DropdownMenuItem>)}
              <DropdownMenuItem onSelect={toggleColorblind}>{isColorblind ? 'Colorblind ON' : 'Colorblind Mode'}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </header>
  );
};
