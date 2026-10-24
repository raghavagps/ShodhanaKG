import { lazy, Suspense, type ReactNode } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Route, Routes } from "react-router-dom";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { Toaster } from "@/components/ui/toaster";
import { TooltipProvider } from "@/components/ui/tooltip";
import { AppLayout } from "@/components/layout/AppLayout";
import { ColorblindProvider } from "@/context/ColorblindProvider";

const AdvancedSearch = lazy(() => import("./pages/AdvancedSearch"));
const Index = lazy(() => import("./pages/Index.tsx"));
const DiseaseExplorer = lazy(() => import("./pages/explorers/DiseaseExplorer"));
const DiseaseTableView = lazy(() => import("./pages/explorers/DiseaseTableView"));
const CompoundExplorer = lazy(() => import("./pages/explorers/CompoundExplorer"));
const CompoundTableView = lazy(() => import("./pages/explorers/CompoundTableView"));
const IntegratedExplorer = lazy(() => import("./pages/explorers/IntegratedExplorer"));
const IntegratedTableView = lazy(() => import("./pages/explorers/IntegratedTableView"));
const SearchResults = lazy(() => import("./pages/SearchResults.tsx"));
const NotFound = lazy(() => import("./pages/NotFound.tsx"));


const FAQ = lazy(() => import("./pages/FAQ.tsx"));
const HowToUse = lazy(() => import("./pages/HowToUse.tsx"));
const Contact = lazy(() => import("./pages/Contact.tsx"));
const Team = lazy(() => import("./pages/Team.tsx"));

const queryClient = new QueryClient();
const DiseaseGraphView = lazy(() => import("./pages/explorers/DiseaseGraphView"));
const CompoundGraphView = lazy(() => import("./pages/explorers/CompoundGraphView"));
const IntegratedGraphView = lazy(() => import("./pages/explorers/IntegratedGraphView"));

const RouteFallback = () => (
  <div className="container py-10 text-sm text-muted-foreground">Loading explorer…</div>
);

const lazyRoute = (node: ReactNode) => (
  <Suspense fallback={<RouteFallback />}>{node}</Suspense>
);

const App = () => (
  <QueryClientProvider client={queryClient}>
    
      <ColorblindProvider>
        <TooltipProvider>
          <Toaster />
          <Sonner />
          <BrowserRouter basename={import.meta.env.BASE_URL}>
            <Routes>
              <Route element={<AppLayout />}>
                <Route path="/" element={lazyRoute(<Index />)} />
                <Route path="/explorer/disease" element={lazyRoute(<DiseaseExplorer />)} />
                <Route path="/explorer/disease/graph" element={lazyRoute(<DiseaseGraphView />)} />
                <Route path="/explorer/disease/table" element={lazyRoute(<DiseaseTableView />)} />
                <Route path="/explorer/compound" element={lazyRoute(<CompoundExplorer />)} />
                <Route path="/explorer/compound/graph" element={lazyRoute(<CompoundGraphView />)} />
                <Route path="/explorer/compound/table" element={lazyRoute(<CompoundTableView />)} />
                <Route path="/explorer/integrated" element={lazyRoute(<IntegratedExplorer />)} />
                <Route path="/explorer/integrated/graph" element={lazyRoute(<IntegratedGraphView />)} />
                <Route path="/explorer/integrated/table" element={lazyRoute(<IntegratedTableView />)} />
                <Route path="/advanced-search" element={lazyRoute(<AdvancedSearch />)} />
              <Route path="/search" element={lazyRoute(<SearchResults />)} />


                <Route path="/faq" element={lazyRoute(<FAQ />)} />
                <Route path="/how-to-use" element={lazyRoute(<HowToUse />)} />
                <Route path="/contact" element={lazyRoute(<Contact />)} />
                <Route path="/team" element={lazyRoute(<Team />)} />
              </Route>
              <Route path="*" element={lazyRoute(<NotFound />)} />
            </Routes>
          </BrowserRouter>
        </TooltipProvider>
      </ColorblindProvider>
    
  </QueryClientProvider>
);

export default App;
