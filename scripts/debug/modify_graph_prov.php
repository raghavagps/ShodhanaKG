<?php
$file = 'src/components/explorer/GraphProvenance.tsx';
$content = file_get_contents($file);

$insert = <<<'EOD'
import { useCompoundProvenance } from "@/lib/api";

export const useCompoundProvenanceHighlighting = (compoundId: string | null) => {
  const { data } = useCompoundProvenance(compoundId);

  const provenanceEdgeIds = useMemo(() => {
    if (!data?.paths) return [];
    
    return data.paths.map((path) => ({
      edge1Id: path.edge1.id,
      edge2Id: path.edge2.id,
      pubmedId: path.provenance.pubmedId || '',
    }));
  }, [data]);

  return {
    provenanceEdgeIds,
    paths: data?.paths || [],
    meta: data?.meta,
  };
};

EOD;

$content = str_replace('export const filterGraphByProvenance', $insert . "export const filterGraphByProvenance", $content);
file_put_contents($file, $content);
echo "Added useCompoundProvenanceHighlighting to GraphProvenance.tsx\n";
