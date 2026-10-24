import { useState, useRef, useEffect, KeyboardEvent, useCallback } from "react";
import { Search, X, ChevronDown } from "lucide-react";
import { cn } from "@/lib/utils";
import { useGraphEntities } from '@/hooks/useGraphEntities';

export interface Option {
  id: string;
  label: string;
}

interface Props {
  label: string;
  options: Option[];
  selected: string[];
  onChange: (next: string[]) => void;
  placeholder?: string;
  accentClass?: string;
  accentColor?: string;
  onSearch?: (query: string) => Promise<Option[]>;
}

export const MultiAutocomplete = ({
  label,
  options,
  selected,
  onChange,
  placeholder = "Type to search…",
  accentClass = "bg-herbal",
  accentColor,
  onSearch,
}: Props) => {
  const selectedEntities = useGraphEntities(selected);
  const [query, setQuery] = useState("");
  const [open, setOpen] = useState(false);
  const [highlight, setHighlight] = useState(0);
  const [searchResults, setSearchResults] = useState<Option[] | null>(null);
  const [searching, setSearching] = useState(false);
  const [dropStyle, setDropStyle] = useState<React.CSSProperties | null>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const searchTimer = useRef<ReturnType<typeof setTimeout>>();
  const searchSeq = useRef(0);

  const SEEN = useRef<Map<string, Option>>(new Map());
  const allKnown: Option[] = options.length > 0
    ? options
    : Array.from(SEEN.current.values());

  useEffect(() => {
    selected.forEach((id) => {
      if (!SEEN.current.has(id)) {
        const found = options.find((o) => o.id === id) ?? searchResults?.find((o) => o.id === id);
        if (found) SEEN.current.set(id, found);
      }
    });
  }, [selected, options, searchResults]);

  useEffect(() => {
    const onClick = (e: MouseEvent) => {
      if (!containerRef.current?.contains(e.target as Node)) {
        setOpen(false);
        setDropStyle(null);
      }
    };
    document.addEventListener("mousedown", onClick);
    return () => document.removeEventListener("mousedown", onClick);
  }, []);

  useEffect(() => {
    if (!open) return;

    const closeMenu = (e?: Event) => {
      if (e?.type === "scroll" && containerRef.current?.contains(e.target as Node)) return;
      setOpen(false);
      setDropStyle(null);
    };

    window.addEventListener("resize", closeMenu);
    window.addEventListener("scroll", closeMenu, true);
    return () => {
      window.removeEventListener("resize", closeMenu);
      window.removeEventListener("scroll", closeMenu, true);
    };
  }, [open]);

  useEffect(() => {
    if (searchTimer.current) clearTimeout(searchTimer.current);
    if (!onSearch || !query.trim()) {
      searchSeq.current += 1;
      setSearchResults(null);
      setSearching(false);
      return;
    }
    const seq = searchSeq.current + 1;
    searchSeq.current = seq;
    setSearching(true);
    searchTimer.current = setTimeout(async () => {
      try {
        const results = await onSearch(query);
        if (searchSeq.current !== seq) return;
        results.forEach((r) => SEEN.current.set(r.id, r));
        setSearchResults(results);
      } catch {
        if (searchSeq.current !== seq) return;
        setSearchResults([]);
      }
      if (searchSeq.current === seq) setSearching(false);
    }, 250);
    return () => { if (searchTimer.current) clearTimeout(searchTimer.current); };
  }, [query, onSearch]);

  const dropdownOptions: Option[] = onSearch
    ? (searchResults ?? [])
    : options;

  const filtered = dropdownOptions
    .filter((o) => !selected.includes(o.id))
    .filter((o) => !onSearch || o.label.toLowerCase().includes(query.toLowerCase()))
    .slice(0, 8);

  const selectedOpts = selected
    .map((id) => ({ id, label: selectedEntities[id]?.node?.label ?? allKnown.find(o => o.id === id)?.label ?? (selectedEntities[id]?.error ? 'Entity unavailable' : 'Loading entity…') }));

  const add = (id: string) => {
    onChange([...selected, id]);
    setQuery("");
    setHighlight(0);
    setSearchResults(null);
  };
  const remove = (id: string) => onChange(selected.filter((x) => x !== id));

  const openMenu = useCallback(() => {
    if (containerRef.current) {
      const r = containerRef.current.getBoundingClientRect();
      const viewportH = window.innerHeight;
      const margin = 8;
      const preferredMax = 224;
      const spaceBelow = viewportH - r.bottom - margin;
      const spaceAbove = r.top - margin;
      const openAbove = spaceBelow < 140 && spaceAbove > spaceBelow;
      const maxHeight = Math.max(120, Math.min(preferredMax, openAbove ? spaceAbove - 6 : spaceBelow - 6));

      setDropStyle({
        position: "fixed",
        left: r.left,
        width: r.width,
        zIndex: 9999,
        maxHeight,
        ...(openAbove ? { bottom: viewportH - r.top + 4 } : { top: r.bottom + 4 }),
      });
    }
    setOpen(true);
  }, []);

  const onKey = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "ArrowDown") {
      e.preventDefault();
      setHighlight((h) => Math.min(h + 1, filtered.length - 1));
      if (!open) openMenu();
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      setHighlight((h) => Math.max(h - 1, 0));
    } else if (e.key === "Enter") {
      e.preventDefault();
      if (filtered[highlight]) add(filtered[highlight].id);
    } else if (e.key === "Backspace" && !query && selected.length) {
      remove(selected[selected.length - 1]);
    } else if (e.key === "Escape") {
      setOpen(false);
      setDropStyle(null);
    }
  };

  return (
    <div ref={containerRef}>
      <div className="flex items-center justify-between mb-1.5">
        <label className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
          <span className={cn("h-2 w-2 rounded-full", accentClass)} style={accentColor ? { backgroundColor: accentColor } : undefined} />
          {label}
        </label>
        {selected.length > 0 && (
          <button
            onClick={() => onChange([])}
            className="text-[10px] text-muted-foreground hover:text-foreground"
          >
            Clear
          </button>
        )}
      </div>

      <div
        className={cn(
          "min-h-9 rounded-md border border-border bg-background px-2 py-1.5 transition-smooth cursor-text",
          open && "border-herbal/60 ring-2 ring-herbal/15"
        )}
        onClick={() => openMenu()}
      >
        {selectedOpts.length > 0 && (
          <div className="flex flex-wrap gap-1 mb-1.5">
            {selectedOpts.map((o) => (
              <span
                key={o.id}
                className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-secondary text-[11px] font-medium text-foreground"
              >
                {o.label}
                <button
                  onClick={(e) => {
                    e.stopPropagation();
                    remove(o.id);
                  }}
                  className="text-muted-foreground hover:text-foreground"
                >
                  <X className="h-3 w-3" />
                </button>
              </span>
            ))}
          </div>
        )}
        <div className="flex items-center gap-1">
          <Search className="h-3 w-3 text-muted-foreground shrink-0" />
          <input
            ref={inputRef}
            value={query}
            onChange={(e) => {
              setQuery(e.target.value);
              if (!open) openMenu();
              setHighlight(0);
            }}
            onFocus={() => openMenu()}
            onKeyDown={onKey}
            placeholder={selected.length ? "" : placeholder}
            className="flex-1 bg-transparent text-xs outline-none placeholder:text-muted-foreground/70 py-0.5"
          />
          <ChevronDown className="h-3 w-3 text-muted-foreground shrink-0" />
        </div>
      </div>

      {open && filtered.length > 0 && dropStyle && (
        <div
          className="overflow-y-auto rounded-md border border-border bg-popover shadow-elevated py-1"
          style={dropStyle}
        >
          {filtered.map((o, i) => (
            <button
              key={o.id}
              onMouseEnter={() => setHighlight(i)}
              onClick={() => add(o.id)}
              className={cn(
                "w-full text-left px-3 py-1.5 text-xs",
                i === highlight
                  ? "bg-secondary text-foreground"
                  : "text-foreground/80 hover:bg-secondary/60"
              )}
            >
              {o.label}
            </button>
          ))}
        </div>
      )}

      {open && searching && filtered.length === 0 && dropStyle && (
        <div
          className="rounded-md border border-border bg-popover shadow-elevated py-2 px-3 text-xs text-muted-foreground"
          style={dropStyle}
        >
          Searching…
        </div>
      )}

      {open && query && !searching && filtered.length === 0 && dropStyle && (
        <div
          className="rounded-md border border-border bg-popover shadow-elevated py-2 px-3 text-xs text-muted-foreground"
          style={dropStyle}
        >
          No matches
        </div>
      )}

      {open && !query && onSearch && filtered.length === 0 && dropStyle && (
        <div
          className="rounded-md border border-border bg-popover shadow-elevated py-2 px-3 text-xs text-muted-foreground"
          style={dropStyle}
        >
          Type to search…
        </div>
      )}
    </div>
  );
};
