# Coordinated architecture and chatbot figures

## 09 — Three-tier system architecture

[Editable SVG](core/09_three_tier_architecture.svg) · [Vector PDF](core/09_three_tier_architecture.pdf) · [Preview](previews/09_three_tier_architecture.png)

**Caption:** ShodhanaKG separates browser presentation, PHP application services on Apache, and an ArcadeDB knowledge graph. Static build files are delivered separately from runtime HTTP requests and JSON/SSE responses. Application services preserve graph direction, properties and edge-bound references. The domain summaries highlight the schema-level Purified Compound–USED_FOR_TREATMENT–Disease connection; they do not depict the complete instance graph. Optional Ollama assistance does not enumerate factual results.

## 10 — Chatbot workflow and architecture connection

[Editable SVG](core/10_chatbot_architecture_pipeline.svg) · [Vector PDF](core/10_chatbot_architecture_pipeline.pdf) · [Preview](previews/10_chatbot_architecture_pipeline.png)

**Caption:** The main path proceeds left to right across two rows, from input/context validation and entity resolution to supported traversal planning, fresh retrieval, provenance validation and deterministic answer construction. Matching [1], [2] and [3] labels identify the browser, PHP and database tiers in Figure 09. Ambiguity returns a clarification request; unsupported paths or absent records produce explicit responses. Structured context interprets subsequent turns but does not replace fresh retrieval or supply scientific evidence. Optional language assistance receives validated facts and cannot enumerate retrieved results. Branches are conceptual, not an exhaustive control-flow specification or measured timing diagram.

## Reproduction and checks

From the repository root:

```powershell
& .audit-temp/figure-env/Scripts/python.exe -B plot_architecture_figures.py
```

Uses the existing isolated Python plotting environment and `requirements-figures.txt`. All icons are local vector primitives; Arial is a locally available font. No external assets are fetched. SVG labels remain editable text and PDFs contain vector art. PNGs are previews only. Both SVGs were parsed, checked for raster/external images, and visually inspected; unsupported tier glyphs and icon/heading spacing were corrected. Validation and implementation-source hashes are in `architecture_validation.json`.

The exports are editable, large-format masters. Check typography at the journal's final printed dimensions before shrinking them. Submit PDF where SVG is not accepted. These diagrams add two core figures without altering the existing benchmark results, KG data, or original figure collection archive. The separate `architecture_figures.zip` contains this pair and its generator.
