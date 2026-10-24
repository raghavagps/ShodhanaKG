import { createContext } from 'react';
export const ChatPresentation = createContext<{
  host: HTMLElement | null;
  sheetOwner: string | null;
  setSheetOwner: (owner: string | null) => void;
} | null>(null);
