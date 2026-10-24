import { EdgeProperties } from "./EdgeProperties";
import { referenceKey } from "@/lib/references";
import { ReferenceLinks } from "./ReferenceLinks";
import { useState, useMemo } from "react";
import { Loader2, ArrowRight, BookOpen, GitBranch, CheckCircle2, Filter } from "lucide-react";
import { useCompoundProvenance } from "@/lib/api";
import type { CompoundProvenancePath } from "@/lib/types";
import { Separator } from "@/components/ui/separator";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

interface Props {
  compoundId: string;
  compoundName?: string;
}

const PathCard = ({ path }: { path: CompoundProvenancePath }) => {
  return (
    <div className="rounded-lg border bg-card p-4 space-y-3 w-full overflow-hidden">
      <section aria-label="Edge properties" className="space-y-2">
        <div><p className="text-xs font-medium">First relationship</p><EdgeProperties properties={path.edge1.display_properties} phase={path.edge1.treatment_phase} /></div>
        <div><p className="text-xs font-medium">Second relationship</p><EdgeProperties properties={path.edge2.display_properties} phase={path.edge2.treatment_phase} /></div>
      </section>
      {/* Provenance Badge */}
      {path.provenance && (
        <div className="flex flex-wrap items-start justify-between gap-2 w-full">
          <TooltipProvider>
            <Tooltip>
              <TooltipTrigger asChild>
                <Badge variant="secondary" className="gap-1.5 shrink-0">
                  <CheckCircle2 className="h-3 w-3 text-green-500" />
                  Provenance Verified
                </Badge>
              </TooltipTrigger>
              <TooltipContent>
                <p className="text-xs max-w-[200px]">
                  Both edges share Source ID {(path.provenance.reference ?? path.provenance.pubmedId)}, confirming co-citation in the same literature
                </p>
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>
          <span className="text-[10px] text-muted-foreground shrink-0 text-right">
            <ReferenceLinks references={path.provenance.references} raw={path.provenance.reference ?? (path.provenance.reference ?? path.provenance.pubmedId)} />
          </span>
        </div>
      )}

      {/* Path Visualization */}
      <div className="space-y-2 min-w-0 w-full">
        {/* Compound */}
        <div className="flex items-center gap-3 min-w-0 w-full">
          <div className="h-8 w-8 rounded-full bg-[hsl(130,22%,35%)] flex items-center justify-center shrink-0">
            <span className="text-xs font-semibold text-white">C</span>
          </div>
          <div className="min-w-0 flex-1">
            <p className="text-sm font-medium truncate">{path.compound.label}</p>
            <p className="text-[10px] text-muted-foreground truncate">{path.compound.type}</p>
          </div>
        </div>

        {/* Edge 1 (UNDERGOES) */}
        <div className="flex items-center gap-3 pl-4">
          <div className="flex flex-col items-center">
            <div className="w-0.5 h-4 bg-border" />
            <ArrowRight className="h-3 w-3 text-muted-foreground rotate-90" />
          </div>
          <div className="flex flex-col gap-1 min-w-0">
            <div className="text-xs text-muted-foreground bg-muted px-2 py-1 rounded inline-block w-max">
              {path.edge1.type.replace(/_/g, ' ')}
            </div>
            {!path.edge1.display_properties && path.edge1.procedure && (
              <span className="text-[10px] text-muted-foreground truncate max-w-[200px]">
                Proc: {path.edge1.procedure}
              </span>
            )}
          </div>
        </div>

        {/* Shodhana Method */}
        <div className="flex items-center gap-3 min-w-0 w-full">
          <div className="h-8 w-8 rounded-full bg-[hsl(16,45%,50%)] flex items-center justify-center shrink-0">
            <span className="text-xs font-semibold text-white">M</span>
          </div>
          <div className="min-w-0 flex-1">
            <p className="text-sm font-medium truncate">{path.shodhanaMethod.label}</p>
            <p className="text-[10px] text-muted-foreground truncate">{path.shodhanaMethod.type}</p>
          </div>
        </div>

        {/* Edge 2 (USES_MEDIA) */}
        <div className="flex items-center gap-3 pl-4">
          <div className="flex flex-col items-center">
            <div className="w-0.5 h-4 bg-border" />
            <ArrowRight className="h-3 w-3 text-muted-foreground rotate-90" />
          </div>
          <div className="flex flex-col gap-1 min-w-0">
            <div className="text-xs text-muted-foreground bg-muted px-2 py-1 rounded inline-block w-max">
              {path.edge2.type.replace(/_/g, ' ')}
            </div>
            {!path.edge2.display_properties && path.edge2.procedure && (
              <span className="text-[10px] text-muted-foreground truncate max-w-[200px]">
                Proc: {path.edge2.procedure}
              </span>
            )}
          </div>
        </div>

        {/* Media */}
        <div className="flex items-center gap-3 min-w-0 w-full">
          <div className="h-8 w-8 rounded-full bg-[hsl(200,28%,45%)] flex items-center justify-center shrink-0">
            <span className="text-xs font-semibold text-white">Md</span>
          </div>
          <div className="min-w-0 flex-1">
            <p className="text-sm font-medium truncate">{path.media.label}</p>
            <p className="text-[10px] text-muted-foreground truncate">{path.media.type}</p>
          </div>
        </div>
      </div>

      {/* Description */}
      {(path.edge2.procedure || path.media.description) && (
        <>
          <Separator />
          <div className="space-y-2 text-xs text-muted-foreground">
            {!path.edge2.display_properties && path.edge2.procedure && (
              <p><span className="font-medium text-foreground">Procedure:</span> {path.edge2.procedure}</p>
            )}
            {path.media.description && (
              <p><span className="font-medium text-foreground">Media:</span> {path.media.description}</p>
            )}
          </div>
        </>
      )}
    </div>
  );
};

export const CompoundProvenancePanel = ({ compoundId, compoundName }: Props) => {
  const [selectedPubmedId, setSelectedPubmedId] = useState<string>("all");
  const { data, isLoading, error } = useCompoundProvenance(compoundId);

  const paths = useMemo(() => data?.paths ?? [], [data?.paths]);
  const meta = data?.meta;

  const uniquePubmedIds = useMemo(() => {
    const ids = new Set<string>();
    paths.forEach(p => {
      if (referenceKey(p.provenance)) {
        ids.add(referenceKey(p.provenance));
      }
    });
    return Array.from(ids).sort();
  }, [paths]);

  const filteredPaths = useMemo(() => {
    if (selectedPubmedId === "all") return paths;
    return paths.filter(p => referenceKey(p.provenance) === selectedPubmedId);
  }, [paths, selectedPubmedId]);

  if (isLoading) {
    return (
      <div className="h-full flex flex-col items-center justify-center text-muted-foreground">
        <Loader2 className="h-6 w-6 animate-spin mb-3" />
        <p className="text-sm">Loading provenance paths...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="h-full flex flex-col items-center justify-center text-center px-6">
        <p className="text-sm font-medium text-red-500">Error loading provenance data</p>
        <p className="text-xs text-muted-foreground mt-1">{error.message}</p>
      </div>
    );
  }

  if (paths.length === 0) {
    return (
      <div className="h-full flex flex-col items-center justify-center text-center px-6 text-muted-foreground">
        <BookOpen className="h-6 w-6 mb-3 opacity-60" />
        <p className="text-sm font-medium text-foreground">No provenance paths found</p>
        <p className="text-xs mt-1 max-w-[240px]">
          No Shodhana Method Connections with matching Source IDs found for {compoundName || 'this compound'}.
        </p>
      </div>
    );
  }

  return (
    <div className="h-full w-full overflow-y-auto overflow-x-hidden flex flex-col">
      {/* Header */}
      <div className="p-4 border-b space-y-3 shrink-0 min-w-0 w-full">
        <div className="flex items-center justify-between gap-2 min-w-0 w-full">
          <div className="min-w-0 flex-1">
            <h3 className="font-display text-lg font-semibold truncate">Provenance Paths</h3>
            <p className="text-xs text-muted-foreground truncate">
              {meta?.totalPaths || paths.length} paths found {uniquePubmedIds.length > 0 ? `· ${uniquePubmedIds.length} unique Source IDs` : ''}
            </p>
          </div>
          <TooltipProvider>
            <Tooltip>
              <TooltipTrigger asChild>
                <Button variant="ghost" size="icon" className="h-8 w-8 shrink-0">
                  <GitBranch className="h-4 w-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>
                <p className="text-xs max-w-[220px]">
                  Each path shows a Compound ShodhanaMethod Media connection where both edges cite the same Source ID
                </p>
              </TooltipContent>
            </Tooltip>
          </TooltipProvider>
        </div>

        {/* Filter */}
        {uniquePubmedIds.length > 1 && (
          <div className="flex items-center gap-2">
            <Filter className="h-3.5 w-3.5 text-muted-foreground" />
            <Select value={selectedPubmedId} onValueChange={setSelectedPubmedId}>
              <SelectTrigger className="h-8 text-xs flex-1">
                <SelectValue placeholder="Filter by Source ID" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Source IDs ({uniquePubmedIds.length})</SelectItem>
                {uniquePubmedIds.map(id => (
                  <SelectItem key={id} value={id}>
                    Source ID: {id}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        )}
      </div>

      {/* Paths List */}
      <div className="flex-1 w-full min-w-0">
        <div className="p-4 space-y-4 w-full min-w-0">
          {filteredPaths.map((path, index) => (
            <PathCard key={`${path.edge1.id}-${path.edge2.id}-${index}`} path={path} />
          ))}
          
          {filteredPaths.length === 0 && selectedPubmedId !== "all" && (
            <p className="text-center text-sm text-muted-foreground py-8">
              No paths match the selected filter
            </p>
          )}
        </div>
      </div>

      {/* Footer */}
      <div className="p-3 border-t bg-muted/50">
        <p className="text-[10px] text-muted-foreground text-center">
          Query time: {meta?.queryMs || 0}ms · Co-citation verified
        </p>
      </div>
    </div>
  );
};

export default CompoundProvenancePanel;
