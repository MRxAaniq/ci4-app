# IMS Frontend (Vue 3 + Vite)

This is the Vue.js frontend for the CodeIgniter 4 Multi-Branch Inventory & Order Management System.

## Run locally

1) Install deps:

```bash
npm install
```

2) Start backend (in repo root):

```bash
php spark serve
```

3) Start frontend (in this folder):

```bash
npm run dev
```

The Vite dev server proxies `/api/*` to the backend (default `http://localhost:8080`) so session cookies work without extra CORS configuration.
