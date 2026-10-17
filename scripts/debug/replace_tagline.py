import re

def replace_in_file(filepath, old_text, new_text, flags=0):
    with open(filepath, "r", encoding="utf-8") as f:
        content = f.read()
    new_content = re.sub(old_text, new_text, content, flags=flags)
    with open(filepath, "w", encoding="utf-8") as f:
        f.write(new_content)

base = "D:/ShodhanaUI/shodhanakg-explorer-suite-main - final-copy"

# 1. FAQ.tsx
replace_in_file(f"{base}/src/pages/FAQ.tsx", 
                r"Ayurvedic Biomedical Knowledge Graph", 
                "Integrative Ayurvedic Purification Knowledge Graph")

# 2. Team.tsx
replace_in_file(f"{base}/src/pages/Team.tsx", 
                r"Ayurvedic Biomedical Knowledge Graph", 
                "Integrative Ayurvedic Purification Knowledge Graph")

# 3. AppLayout.tsx
replace_in_file(f"{base}/src/components/layout/AppLayout.tsx", 
                r"An Ayurvedic Biomedical Knowledge Graph", 
                "An Integrative Ayurvedic Purification Knowledge Graph")

# 4. uiTranslations.ts
# Original english: "Ayurvedic biomedical knowledge graph"
# Original hindi: "आयुर्वेदिक बायोमेडिकल नॉलेज ग्राफ"
replace_in_file(f"{base}/src/data/translations/uiTranslations.ts", 
                r'"Ayurvedic biomedical knowledge graph"', 
                '"Integrative Ayurvedic purification knowledge graph"', flags=re.IGNORECASE)

replace_in_file(f"{base}/src/data/translations/uiTranslations.ts", 
                r'"आयुर्वेदिक बायोमेडिकल नॉलेज ग्राफ"', 
                '"एकीकृत आयुर्वेदिक शोधन नॉलेज ग्राफ"')

print("Replacements completed.")
