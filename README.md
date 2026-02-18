# PHP Changelog — simple changelog web app (PHP + SQLite)

A small server-backed changelog application with a browser UI and a tiny JSON API. The app is implemented in PHP, stores data in SQLite, and is packaged for local run using Docker Compose.

✅ Key features

- Submit changelog entries (title, description, submitter, tags)
- List and filter entries by date range (`from` / `to`), `submitter`, and `tags`
- Persisted storage using SQLite (`php-app/data/changelog.db`)
- Lightweight JSON API for automation or scripting

---

## Quick start (Docker Compose)

```bash
# build and run (default host port 8080)
docker compose up --build -d
# open http://localhost:8080/
```

To change the host port, edit `ports` in `docker-compose.yml` (default `8080:80`) or map a different host port when running the container.

---

## What this repository contains

- `php-app/src/` — PHP web UI (`/`) and API endpoints under `/api`
  - `api/entries.php` — GET (list/filter) and POST (create)
  - `api/tags.php` — returns tags from `php-app/data/tags.csv`
- `php-app/data/` — runtime data (SQLite DB and `tags.csv`)
- `docker-compose.yml` — runs the PHP app with a bind mount for `src` and `data`

---

## API

- POST /api/entries.php
  - Create an entry. Accepts JSON body or form fields.
  - Required: `title`. Optional: `description`, `submitter`, `tags` (array or comma-separated), `timestamp` (ms or ISO).
  - Returns created entry (JSON) with `id` and `timestamp` (milliseconds).

- GET /api/entries.php
  - List entries. Query params:
    - `from`, `to` — ISO datetime or epoch (ms) to filter by timestamp
    - `submitter` — partial match
    - `tags` — comma-separated tags (matches entries that contain each tag)
    - `sort` — `timestamp` (default), `submitter`, `tags`, `title`
    - `order` — `asc` or `desc` (default `desc`)
  - Returns JSON array of entries. `tags` are returned as an array.

- GET /api/tags.php
  - Returns tags from `php-app/data/tags.csv` as JSON: [{"tag":"...","hex":"..."}, ...]

OpenAPI / machine-readable spec: `openapi.yaml` — importable into Swagger / Redoc / tooling.

Notes: API responses are JSON. There is no authentication or CORS headers by default.

---

## Data format & storage

- Database: `php-app/data/changelog.db` (SQLite). Table `entries` stores: `id`, `title`, `description`, `submitter`, `tags`, `timestamp`.
- Tags in the DB are stored as a CSV string with surrounding commas (e.g. `,bug,ui,`) to allow fast LIKE-based matching.
- `timestamp` is stored as milliseconds since the UNIX epoch.

---

## Example usage

Create an entry:

```bash
curl -sS -X POST http://localhost:8080/api/entries.php \
  -H 'Content-Type: application/json' \
  -d '{"title":"Fix bug","description":"details","submitter":"alice","tags":["bug","ui"]}'
```

Query entries (filter by tag, sorted by submitter ascending):

```bash
curl 'http://localhost:8080/api/entries.php?tags=bug&sort=submitter&order=asc'
```

Get available tags:

```bash
curl http://localhost:8080/api/tags.php
```

---

## Development & requirements

- Requires PHP with PDO and `pdo_sqlite` enabled (the app checks for these and returns JSON errors if missing).
- The code is mounted from `php-app/src` and data from `php-app/data` when using Docker Compose.
- To run without Compose for quick testing:

```bash
docker run -p 8080:80 --rm -v "$PWD/php-app/src":/var/www/html -v "$PWD/php-app/data":/var/www/html/data php:8.2-apache
```

---

## Security & notes

- No authentication; do not expose to untrusted networks without adding auth/reverse proxy and TLS.
- Timestamps are milliseconds; tags are matched using SQL LIKE queries.

If you want, I can add API docs, authentication, or a small migration to a separate database backend. 🔧
