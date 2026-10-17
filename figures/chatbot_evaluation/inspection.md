# Figure inspection — 11 October 2026

The generator was executed with the existing local figure environment. Eight input artifacts matched the original audit manifest; all four latency summaries were recomputed from raw observations. No new evaluation or KG retrieval was run.

The normal PNG exports and the grayscale/protanopia/deuteranopia/tritanopia contact sheets were visually inspected. The initial missing diamond font glyph was replaced by a textual marker key; the endpoint summary was moved clear of the axis label; the main panel label was aligned with its heading. Final figures have no observed clipping or label collisions. Statistical point overlap in the paired timing plot is intentional: all 54 observations remain present, without jitter or sampling. Similar JSON/SSE cumulative distributions overlap where their measured values coincide; line styles and the paired panel expose the comparison without altering values.

Programmatic checks confirm a viewBox, editable SVG text, native vector paths, no SVG raster images/scripts, no out-of-canvas labels, embedded TrueType PDF fonts, no PDF raster image objects, and 300-dpi RGB PNG exports. All three figures are 183 mm wide; heights are 135, 170 and 155 mm. Their 7–10 pt type is centered around the guideline's approximately 8 pt recommendation. Physical print/proof inspection was not performed; the final journal proof should be checked at 100% scale.

Color-vision simulations are approximate diagnostic previews, not clinical accessibility validation. Direct labels, marker shapes, distinct line styles and exact counts carry meaning independently of color. PDF is the intended submission format; SVG is the editable master because the supplied journal guidance does not accept SVG for final submission.

Only the figure generator and figure artifacts/documentation were added. Application build/tests were not rerun for this isolated figure-generation task. Backend, frontend, fixtures, KG and production were untouched.
