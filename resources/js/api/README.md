# API layer

`client.ts` is the shared HTTP entry point for the Laravel API.

Rules:

- Keep page components free from raw `fetch()` calls.
- Send `X-Workspace-Id` for workspace-scoped requests.
- Use cookie authentication for the first-party web app.
- Keep demo data behind dedicated demo adapters; do not mix fake rows into API responses.
- Convert each module from demo data to API data one module at a time.
