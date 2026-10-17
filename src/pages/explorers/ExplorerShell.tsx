import { Search, SlidersHorizontal, Network } from "lucide-react";
import { ReactNode } from "react";

interface Props {
  title: string;
  kicker: string;
  description: string;
  accent?: "herbal" | "terracotta" | "bark";
  children?: ReactNode;
}

const accentMap = {
  herbal: "text-herbal",
  terracotta: "text-terracotta",
  bark: "text-bark",
};

export const ExplorerShell = ({ title, kicker, description, accent = "herbal", children }: Props) => {
  return (
    <section className="container py-10 md:py-14">
      <div className="mb-8">
        <div className={`text-xs font-medium uppercase tracking-[0.18em] mb-2 ${accentMap[accent]}`}>
          {kicker}
        </div>
        <h1 className="font-display text-3xl md:text-4xl font-semibold text-foreground">
          {title}
        </h1>
        <p className="mt-3 max-w-2xl text-muted-foreground leading-relaxed">{description}</p>
      </div>

      {/* Search bar */}
      <div className="rounded-xl border border-border bg-card p-4 shadow-card mb-6 flex items-center gap-3">
        <Search className="h-4 w-4 text-muted-foreground shrink-0 ml-1" />
        <input
          placeholder={`Search ${kicker.toLowerCase()}…`}
          className="flex-1 bg-transparent outline-none text-sm placeholder:text-muted-foreground"
        />
        <button className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground px-3 py-1.5 rounded-md hover:bg-secondary transition-smooth">
          <SlidersHorizontal className="h-3.5 w-3.5" />
          Filters
        </button>
      </div>

      <div className="grid gap-6 lg:grid-cols-[1fr_280px]">
        <div className="rounded-xl border border-border bg-card shadow-card min-h-[420px] flex flex-col items-center justify-center p-10 text-center">
          <span className="flex h-14 w-14 items-center justify-center rounded-full bg-secondary text-herbal mb-4">
            <Network className="h-6 w-6" strokeWidth={1.6} />
          </span>
          <h3 className="font-display text-lg font-semibold mb-1.5">Knowledge graph canvas</h3>
          <p className="text-sm text-muted-foreground max-w-sm">
            Search a term above to render the interactive graph. Nodes, edges, and details will appear here.
          </p>
        </div>

        <aside className="rounded-xl border border-border bg-card shadow-card p-5">
          <div className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">
            Graph filters
          </div>
          <div className="space-y-2 text-sm">
            {["Node type", "Evidence strength", "Source", "Therapy class"].map((f) => (
              <div key={f} className="flex items-center justify-between py-2 border-b border-border/60 last:border-0">
                <span className="text-foreground">{f}</span>
                <span className="text-xs text-muted-foreground">All</span>
              </div>
            ))}
          </div>
        </aside>
      </div>

      {children}
    </section>
  );
};
