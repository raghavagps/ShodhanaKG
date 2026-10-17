import type { ComponentProps } from "react";
import { DetailsPanel } from "./DetailsPanel";
import { ProvenancePanel } from "@/components/explorer/ProvenancePanel";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Info, BookOpen } from "lucide-react";

/**
 * Example: Integrating ProvenancePanel into DiseaseGraphView
 * 
 * In DiseaseGraphView.tsx, replace the right sidebar section (around line 439-457):
 */

// Replace the right sidebar with:
type ExampleProps = ComponentProps<typeof DetailsPanel>;
const RightSidebarExample = (props: ExampleProps) => {
  const { selectedNode } = props;
  // Get the selected disease ID from the graph
  const selectedDiseaseId = selectedNode?.type === "Disease" ? selectedNode.id : undefined;
  
  return (
    <aside className="order-3 rounded-xl border border-border bg-card shadow-card flex flex-col h-full">
      <Tabs defaultValue="properties" className="flex flex-col h-full">
        <div className="px-4 py-3 border-b border-border/70">
          <TabsList className="grid w-full grid-cols-2">
            <TabsTrigger value="properties" className="gap-1.5">
              <Info className="h-3.5 w-3.5" />
              Properties
            </TabsTrigger>
            <TabsTrigger value="provenance" className="gap-1.5">
              <BookOpen className="h-3.5 w-3.5" />
              Provenance
            </TabsTrigger>
          </TabsList>
        </div>
        
        <div className="overflow-y-auto flex-1">
          <TabsContent value="properties" className="m-0 h-full">
            <DetailsPanel {...props} />
          </TabsContent>
          
          <TabsContent value="provenance" className="m-0 h-full">
            {selectedDiseaseId ? (
              <ProvenancePanel 
                diseaseId={selectedDiseaseId}
                diseaseName={selectedNode?.label}
              />
            ) : (
              <div className="h-full flex flex-col items-center justify-center text-center px-6 text-muted-foreground">
                <BookOpen className="h-6 w-6 mb-3 opacity-60" />
                <p className="text-sm font-medium text-foreground">No disease selected</p>
                <p className="text-xs mt-1 max-w-[220px]">
                  Select a disease node in the graph to view its provenance paths.
                </p>
              </div>
            )}
          </TabsContent>
        </div>
      </Tabs>
    </aside>
  );
};

/**
 * Alternative: Show ProvenancePanel conditionally for Disease nodes
 * 
 * You could also show the ProvenancePanel only when a Disease node is selected:
 */
const ConditionalProvenanceExample = (props: ExampleProps) => {
  const { selectedNode } = props;
  const isDiseaseSelected = selectedNode?.type === "Disease";
  
  return (
    <aside className="order-3 rounded-xl border border-border bg-card shadow-card flex flex-col h-full">
      {isDiseaseSelected ? (
        <>
          <div className="px-4 py-3 border-b border-border/70">
            <h2 className="font-display text-sm font-semibold">Provenance</h2>
          </div>
          <div className="overflow-y-auto flex-1">
            <ProvenancePanel 
              diseaseId={selectedNode.id}
              diseaseName={selectedNode.label}
            />
          </div>
        </>
      ) : (
        <>
          <div className="px-4 py-3 border-b border-border/70">
            <h2 className="font-display text-sm font-semibold">Properties</h2>
          </div>
          <div className="overflow-y-auto flex-1">
            <DetailsPanel {...props} />
          </div>
        </>
      )}
    </aside>
  );
};

export { RightSidebarExample, ConditionalProvenanceExample };
