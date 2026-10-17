import React, { createContext, useContext, useEffect, useState, ReactNode, useMemo } from "react";

interface ColorblindContextType {
  isColorblind: boolean;
  toggleColorblind: () => void;
  /** Resolves a palette based on current mode */
  getPalette: <T extends string>(basePalette: Record<T, string>) => Record<T, string>;
}

export const ColorblindContext = createContext<ColorblindContextType | undefined>(undefined);

export const useColorblind = () => {
  const context = useContext(ColorblindContext);
  if (context === undefined) {
    throw new Error("useColorblind must be used within a ColorblindProvider");
  }
  return context;
};
