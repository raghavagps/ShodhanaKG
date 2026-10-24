# 🚀 Deployment Guide - ShodhanaKG

> For the current institutional subdirectory release, use [the verified package procedure](docs/deployment/RELEASE_PACKAGE.md). The historical instructions below contain older file/schema counts and do not replace the explicit subdirectory build or preservation of production configuration and RID-bound references.

This guide provides step-by-step instructions for deploying the **ShodhanaKG Explorer Suite** to a production environment.

## 🏗 System Architecture
The suite consists of three main components:
1.  **Frontend**: A React application (built with Vite).
2.  **Backend**: A lightweight PHP API service.
3.  **Database**: An ArcadeDB graph database instance.

---

## 1. Database Setup (ArcadeDB)
1.  **Install ArcadeDB**: Download and run ArcadeDB on your server (default port `2480`).
2.  **Import Data**: Ensure your database (e.g., `shodhana`) is created and the vertex/edge data is imported.
3.  **Create User**: Create a read-only user for the API to use.
    ```bash
    # Example ArcadeDB console command
    CREATE USER username IDENTIFIED BY 'password'
    GRANT DATABASE.READ TO username
    ```

---

## 2. Backend Deployment (PHP)
The backend is located in the `webservice/api/` directory (17 PHP files).

1.  **Server Requirements**:
    *   PHP 5.6.40 or higher.
    *   `php-curl` extension (recommended, but has fallback to `file_get_contents` → `fsockopen` if disabled).
    *   Web server (Apache or Nginx) with PHP support.
2.  **Configuration**:
    Set the following environment variables on your server (or via your web server configuration):
    *   `ARCADE_URL`: `http://localhost:2480`
    *   `ARCADE_DB`: `shodhana`
    *   `ARCADE_USER`: `username`
    *   `ARCADE_PASS`: `password`
    *   `ALLOWED_ORIGINS`: `https://your-frontend-domain.com` (for CORS security)
3.  **Upload Checklist**:
    Upload the contents of the `webservice/api/` directory to your web server. Ensure the following critical files are updated:
    *   `db.php`: 3-tier connectivity handler (cURL → `file_get_contents` → `fsockopen` with manual chunked decoding).
    *   `graph_service.php`: Shared schema mapping (17 vertex types, 22 edge types), type normalization, field resolution.
    *   `config.php`: ArcadeDB connection details and allowed origins.
    *   All 11 endpoint files (`search.php`, `graph.php`, `category-stats.php`, `kg-stats.php`, `counts.php`, `bridge-stats.php`, `node.php`, `provenance-subgraph.php`, `compound-provenance.php`, `health.php`, `chat.php`).

---

## 3. AI Chatbot Setup (Ollama)
The chatbot relies on a local LLM hosted via Ollama to ensure data privacy and avoid API costs.

1.  **Install Ollama**: Follow instructions at [ollama.com](https://ollama.com/download) for your OS.
    ```bash
    # Linux installation
    curl -fsSL https://ollama.com/install.sh | sh
    ```
2.  **Pull the Model**: The system defaults to `llama3.2` for its balance of speed and reasoning.
    ```bash
    ollama pull llama3.2
    ```
3.  **Start the Service**: Ensure Ollama is running on the default port `11434`.
    *   Set `OLLAMA_URL="http://localhost:11434"` in your Apache/PHP environment configuration.
    *   If you choose a different model, set `OLLAMA_MODEL="your-model-name"`.

---

## 3. Frontend Deployment (React)
The frontend must be built before deployment to generate optimized static files.

1.  **Configure API URL**:
    Create a file named `.env.production` in the project root:
    ```env
    VITE_API_BASE=/APP_BASE_PATH
    ```
2.  **Build the Project**:
    ```bash
    npm install
    npm run build
    ```
3.  **Upload Static Files**:
    Upload the contents of the `dist/` directory to your web server (e.g., `./` or `dist/`).
4.  **SPA Routing**:
    Ensure your web server redirects all non-file requests to `index.html`.
    *   **Apache**: Use the provided `.htaccess` file.
    *   **Nginx**: Add the following to your site configuration:
        ```nginx
        location /APP_BASE_PATH/ {
            try_files $uri $uri/ /APP_BASE_PATH/index.html;
        }
        ```

---

## 📄 Documentation
- **[Frontend Documentation](./FRONTEND_DOCUMENTATION.md)** — Component tree, state management, graph visualization, page reference.
- **[Backend Documentation](./BACKEND_DOCUMENTATION.md)** — All endpoints, ArcadeDB schema, connection details, field mappings.

## 🛠 Troubleshooting
*   **CORS Issues**: Ensure `ALLOWED_ORIGINS` in the backend matches your frontend URL exactly.
*   **Graph Not Loading**: Check if the PHP backend can reach ArcadeDB via `ARCADE_URL`. Hit `/api/health.php` to verify.
*   **Search Returning Empty**: Check PHP error logs. If ArcadeDB throws "Error on transaction commit", a vertex type name might be mismatched (see `graph_service.php` normalization functions).
*   **404 on Refresh**: Ensure the web server is correctly configured for SPA routing (Step 3.4).

## 📋 Maintenance
*   **Logs**: Monitor PHP error logs and ArcadeDB logs for connectivity issues.
*   **Backups**: Regularly backup your ArcadeDB data using the ArcadeDB backup utility.
*   **Updates**: After backend changes, run `php webservice/api/tests/provenance-subgraph.test.php` to verify core logic.
