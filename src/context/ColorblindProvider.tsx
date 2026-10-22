import { NODE_STYLES, nodeStyle } from '@/lib/nodeStyles';
import { entityType } from '@/lib/entityNavigation';
import React, { useState, useEffect, ReactNode } from "react";
import { ColorblindContext } from "../hooks/useColorblind";

export const ColorblindProvider = ({ children }: { children: ReactNode }) => {
  const [isColorblind, setIsColorblind] = useState(() => {
    return localStorage.getItem("shodhana-colorblind") === "true";
  });

  useEffect(() => {
    const root = document.documentElement;
    root.style.setProperty("--node-AyurvedicDiagnosis",nodeStyle("AyurvedicDiagnosis",isColorblind).fill);
    if (isColorblind) {
      root.classList.add("colorblind");
      localStorage.setItem("shodhana-colorblind", "true");
    } else {
      root.classList.remove("colorblind");
      localStorage.setItem("shodhana-colorblind", "false");
    }
  }, [isColorblind]);

  const toggleColorblind = () => setIsColorblind((prev) => !prev);

  // Hardcoded colorblind palette (matches index.css :root.colorblind)
  const getPalette = React.useCallback(<T extends string>(basePalette: Record<T, string>): Record<T, string> => {
    if (!isColorblind) return basePalette;

    const newPalette = { ...basePalette };
    for (const key in basePalette) {
      if (entityType(key) in NODE_STYLES) {
        newPalette[key] = nodeStyle(key, true).fill;
      }
    }

    return newPalette;
  }, [isColorblind]);

  return (
    <ColorblindContext.Provider value={{ isColorblind, toggleColorblind, getPalette }}>
      {children}
    </ColorblindContext.Provider>
  );
};

