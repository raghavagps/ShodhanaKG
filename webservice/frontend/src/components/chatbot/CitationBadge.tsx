import React from 'react';
import { ExternalLink } from 'lucide-react';

interface CitationBadgeProps {
  pubmedId: string;
}

export const CitationBadge: React.FC<CitationBadgeProps> = ({ pubmedId }) => {
  return (
    <a
      href={`https://pubmed.ncbi.nlm.nih.gov/${pubmedId}/`}
      target="_blank"
      rel="noopener noreferrer"
      className="inline-flex items-center gap-1 px-1.5 py-0.5 ml-1 align-baseline text-[10px] font-medium bg-blue-50 text-blue-600 border border-blue-200 rounded hover:bg-blue-100 transition-colors"
      title={`View PubMed Article ${pubmedId}`}
    >
      PMID: {pubmedId}
      <ExternalLink className="w-2 h-2" />
    </a>
  );
};
