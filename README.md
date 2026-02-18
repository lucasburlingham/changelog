# Static Changelog App

This is a small static single-page app that runs from static hosting (S3, GitHub Pages, etc.). It implements a client-side API at `/api/entries` using a service worker and stores entries in IndexedDB.

## Features

- Submit changelog entries (title, description, submitter, tags)
- GET entries with filtering by date/time (`from`, `to` ISO), `submitter`, and `tags` (comma-separated)

## How it works

- The service worker `sw.js` intercepts requests to `/api/entries` and implements `GET` and `POST` backed by IndexedDB.
- The page `index.html` provides UI and uses `fetch('/api/entries')` and `fetch('/api/entries', {method:'POST'})`.

## Notes & deployment

- Upload all files to your static host root. Ensure `sw.js` and `index.html` are served from the same origin.
- Service workers only control pages on the same origin and only work over HTTPS (or localhost). When hosted on S3 behind CloudFront or similar, ensure HTTPS.
- The API implemented here will respond to requests made by pages controlled by the service worker. It does not create a network-accessible API for arbitrary external curl requests to the S3 URL.

## Usage

1. Open the hosted `index.html` in a browser (HTTPS). The app registers the service worker.
2. Use the form to submit entries. Use the filter form to query by `from`, `to`, `submitter`, or `tags`.

If you need a network-accessible API (so other services can POST directly to the bucket), I can add an alternative approach using lightweight serverless endpoints (Lambda, API Gateway, or S3 object PUTs). Ask if you'd like that.

---

## PHP + Docker Compose (SQLite)

A ready-to-run PHP + SQLite implementation is included in `php-app/`. It provides both a small web UI and an API:

- POST `/api/entries.php`  — create an entry (JSON)
- GET  `/api/entries.php`  — list / filter / sort entries (query params: `from`, `to`, `submitter`, `tags`, `sort`, `order`)

Run locally with Docker Compose:

```bash
docker compose up --build -d
# app -> http://localhost:8080/
```

Change the host port

- Edit the host-side port mapping in `docker-compose.yml` (left side is host, right side is container).
  - Default: `8080:80` (host 8080 → container 80)
  - To use port 9090 change to `9090:80` then recreate the service.

Example — edit `docker-compose.yml` ports section:

```yaml
services:
  php:
    ports:
      - "9090:80"    # host:container
```

Then restart:

```bash
docker compose up -d --build
# open http://localhost:9090/
```

Run on a different port without editing files

```bash
# map host port 9090 -> container 80
docker run -p 9090:80 --rm -v "$PWD/php-app/src":/var/www/html -v "$PWD/php-app/data":/var/www/html/data php:8.2-apache
```

Synology / DSM — step-by-step

1. Copy the project to your Synology (example path `/volume1/docker/changelog`). Use File Station or scp/rsync:

```bash
scp -r . user@<synology-ip>:/volume1/docker/changelog
```

2. SSH to the NAS and enter the folder:

```bash
ssh user@<synology-ip>
cd /volume1/docker/changelog
```

3. Start the service with Docker Compose:

```bash
docker compose up -d --build
```

4. Change host port (two options):
   - Edit `docker-compose.yml` (change `ports: - "8080:80"` → `"9090:80"`) and run `docker compose up -d --build`.
   - Or use DSM: Docker > Container > select container > Edit > Port Settings → change the local port.

5. Fix data permissions if the DB can't be written:

```bash
sudo chown -R http:http /volume1/docker/changelog/php-app/data
sudo chmod -R 755 /volume1/docker/changelog/php-app/data
```

6. Access the app at `http://<synology-ip>:<host-port>`.

7. To update: `git pull` then `docker compose up -d --build`.

Notes: use DSM Reverse Proxy for TLS or configure Application Portal to issue certificates.

Express backend port (optional)

- The Synology/Express backend (if used) respects the `PORT` environment variable. Example to run Express on 3000:

```bash
# set env and start (example for manual docker run / systemd etc.)
export PORT=3000
node backend/server.js
# then map host port -> container port as needed in your deployment
```

Security & notes

- The container always listens on container port `80` by default — changing the host port does not change the container internals.
- Use a reverse proxy (NGINX, Traefik) if you need TLS or virtual hosts on Synology.


Examples

Create an entry:

```bash
curl -sS -X POST http://localhost:8080/api/entries.php \
  -H 'Content-Type: application/json' \
  -d '{"title":"Fix bug","description":"details","submitter":"alice","tags":["bug","ui"]}'
```

Query entries (filter by tag, sort by submitter ascending):

```bash
curl 'http://localhost:8080/api/entries.php?tags=bug&sort=submitter&order=asc'
```

Data is stored in `php-app/data/changelog.db` (SQLite).

Tell me if you want a Synology Docker/DSM runbook, a systemd unit or an automated deploy workflow.
