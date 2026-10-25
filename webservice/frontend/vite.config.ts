import { defineConfig, loadEnv } from "vite";
import react from "@vitejs/plugin-react-swc";
import tailwindcss from "@tailwindcss/vite";
import path from "path";

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => {
  const envDir = path.resolve(__dirname, "../../");
  const env = loadEnv(mode, envDir, "");

  const frontendHost = env.FRONTEND_HOST || "::";
  const frontendPort = env.FRONTEND_PORT ? Number(env.FRONTEND_PORT) : (env.VITE_PORT ? Number(env.VITE_PORT) : 8080);
  const apiHost = env.API_HOST || "127.0.0.1";
  const apiPort = env.API_PORT || "8081";
  const apiProxyTarget = env.API_PROXY_TARGET || `http://${apiHost}:${apiPort}`;

  return {
    envDir,
    base: "/",
    build: {
      rollupOptions: {
        output: {
          manualChunks(id) {
            const modulePath = id.replace(/\\/g, "/");
            if (/\/node_modules\/(?:react|react-dom|scheduler|react-router|react-router-dom|@remix-run\/router|@tanstack\/[^/]+|@radix-ui\/[^/]+)\//.test(modulePath)) return "vendor-interface";
          },
        },
      },
    },
    server: {
      host: frontendHost,
      port: frontendPort,
      // Generated audits and tool caches are not application inputs. Watching
      // them can create tens of thousands of Windows handles and process every
      // trace/cache write while serving pages. Keep source/public HMR intact.
      watch: {
        ignored: ["**/.audit-temp/**", "**/docs/audits/**", "**/api/storage/**", "**/playwright-report/**"],
      },
      hmr: {
        overlay: false,
      },
      proxy: {
        '/api': {
          target: apiProxyTarget,
          changeOrigin: true,
        }
      }
    },
    preview: {
      host: frontendHost,
      port: frontendPort,
    },
    plugins: [react(), tailwindcss()].filter(Boolean),
    resolve: {
      alias: {
        "@": path.resolve(__dirname, "./src"),
      },
      dedupe: ["react", "react-dom", "react/jsx-runtime", "react/jsx-dev-runtime", "@tanstack/react-query", "@tanstack/query-core"],
    },
  };
});
