import { useEffect, useRef, useState } from "react";
import { Download, FileJson, FileSpreadsheet, ImageDown, ChevronDown } from "lucide-react";
import { cn } from "@/lib/utils";

interface Props {
  onJSON: () => void;
  onCSV: () => void;
  onPNG: () => void;
  disabled?: boolean;
}

export const ExportMenu = ({ onJSON, onCSV, onPNG, disabled }: Props) => {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const onDoc = (e: MouseEvent) => {
      if (!ref.current?.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener("mousedown", onDoc);
    return () => document.removeEventListener("mousedown", onDoc);
  }, []);

  const pick = (fn: () => void) => () => {
    setOpen(false);
    fn();
  };

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        disabled={disabled}
        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-border bg-card text-xs font-medium text-foreground hover:bg-secondary hover:-translate-y-0.5 transition-all disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0"
      >
        <Download className="h-3.5 w-3.5" />
        Export
        <ChevronDown className={cn("h-3 w-3 transition-transform", open && "rotate-180")} />
      </button>

      {open && (
        <div className="absolute right-0 mt-1 z-30 w-40 rounded-md border border-border bg-popover shadow-elevated overflow-hidden animate-fade-in">
          <button
            onClick={pick(onCSV)}
            className="w-full flex items-center gap-2 px-3 py-2 text-xs text-foreground hover:bg-secondary/70 transition-smooth"
          >
            <FileSpreadsheet className="h-3.5 w-3.5 text-herbal" />
            Export as CSV
          </button>
          <button
            onClick={pick(onJSON)}
            className="w-full flex items-center gap-2 px-3 py-2 text-xs text-foreground hover:bg-secondary/70 transition-smooth border-t border-border/60"
          >
            <FileJson className="h-3.5 w-3.5 text-bark" />
            Export as JSON
          </button>
          <button
            onClick={pick(onPNG)}
            className="w-full flex items-center gap-2 px-3 py-2 text-xs text-foreground hover:bg-secondary/70 transition-smooth border-t border-border/60"
          >
            <ImageDown className="h-3.5 w-3.5 text-terracotta" />
            Export as PNG
          </button>
        </div>
      )}
    </div>
  );
};
