Restructure this repo by MOVING files only.

Hard rules:
- Do NOT delete any file or folder.
- Do NOT edit file contents, except path references that break because of a move.
- Do NOT invent, assume, or write any new information (no new README text, no new docs, no placeholder content, no guessed destinations).
- If you need information, or a file's destination is unclear, STOP and ask me. List the file and what you need.
- Leave RESTRUCTURE.md in place until I move it.

Target layout:

ShodhanaKG/
├── LICENSE, README.md, .gitignore   (stay in root)
├── webservice/
│   ├── frontend/   <- src, public, index.html, package.json, package-lock.json, and the vite/vitest/tailwind/postcss/eslint/tsconfig*/components.json/playwright config files
│   └── api/        <- current api/ (including its vendor, storage, core, tests)
├── arcadedb/       <- ArcadeDB schema, data exports, import scripts (ask me which files these are)
├── evaluation/     <- Python benchmark scripts and fixtures (ask me which files these are)
└── docs/           <- all documentation and reports from root, except README.md and LICENSE
	├── webservice/    <- docs about the frontend, API, chatbot, deployment, and server setup
    	├── arcadedb/      <- docs about the schema, data, import, and queries
    	├── evaluation/    <- benchmark and test reports
    	└── general/       <- docs that fit none of the above

Leave in place, untouched: node_modules, dist, playwright-report, test-results, .playwright-mcp, .gemini, .github, results, scripts, .env, .env.production, .env.production.example, apache.conf.example.

After moving:
1. Fix only the references broken by the moves (imports, require/include paths, config paths, package.json scripts, CI, doc links).
2. Run `npm ci && npm run build` in webservice/frontend and `php -l` on webservice/api files. Fix only path errors.

Final output:
(a) table of old path -> new path
(b) files left in place
(c) files whose destination you need me to confirm
(d) references you could not update
(e) build results