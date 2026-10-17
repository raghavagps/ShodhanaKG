# Chatbot evaluation figures

One main composite and two supplements derived exclusively from the checksum-verified contract-8 local benchmark. See captions.md for manuscript legends and source_data/ for plotted values. Existing manuscript report: ../../docs/manuscript/chatbot-evaluation-20261011/manuscript_results.md.

Reproduce from the repository root (PowerShell):

```powershell
& .\.audit-temp\figure-env\Scripts\python.exe -B scripts/plot_chatbot_evaluation.py
```

Use --input and --output to override directories. Existing dependencies are documented in requirements-figures.txt; no dependencies were installed or changed. All caches are directed to D: under .audit-temp/chatbot-evaluation-figures. The generator validates original hashes and recomputes quantitative summaries before export; inconsistent sources stop execution.

PDFs are the submission files (embedded TrueType font); SVGs preserve editable text as local vector masters. PNG previews are RGB at 300 dpi. The supplied journal guidance does not accept SVG for submission. Figures are prepared at 183 mm width, Arial approximately 8 pt, white background, no gradients/hatching, lower-case panel labels and strokes >=0.25 pt. No remote assets or font services are used.

previews/ contains normal, grayscale and approximate full-severity protanopia/deuteranopia/tritanopia contact sheets. Matrix previews are diagnostic approximations, not an accessibility certification; labels, line styles and marker shapes preserve interpretation without color. No simulation image is embedded in any vector master.

All cases passed in the selected run; denominators and coverage therefore carry more information than repeated 100% accuracy bars. Suites overlap. Raw distributions are descriptive, not confidence intervals. No clinical truth, RAGAS score, production performance or model-attributable improvement is inferred. Application-wide browser counts and later production smoke tests are intentionally excluded. No new benchmark or live retrieval was run.
