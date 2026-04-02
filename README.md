# PHP Changelog (PHP + SQLite)

A small changelog app with a browser UI and JSON API.

- UI for creating entries with rich-text descriptions
- Filtering and sorting entries
- Tag and submitter suggestions with usage counts
- Dark mode toggle with local preference persistence
- Infinite scrolling entries list (batched loading)
- SQLite-backed persistence
- Docker Compose setup with optional Cloudflare Tunnel

## Quick start

```bash
docker compose up --build -d
```

The app is served by the `php` service on the Docker network. If you expose a local port in your environment, open that URL in your browser.

To watch logs:

```bash
docker compose logs -f php
```

## Optional Cloudflare Tunnel

The `compose.yml` file includes a `cloudflare-tunnel` service.

1. Create a Cloudflare Tunnel and copy the token.
2. Set `CLOUDFLARE_TUNNEL_TOKEN` in your root `.env`.
3. Start services with `docker compose up -d`.
4. View tunnel logs with `docker compose logs -f cloudflare-tunnel`.

Notes:

- The tunnel service depends on `php` and forwards traffic to `http://php:80` inside the Docker network.
- If `CLOUDFLARE_TUNNEL_TOKEN` is not set, the tunnel container will fail to start.

## Configuration

`index.php` reads values in this order:

1. Environment variables (`getenv`, `$_SERVER`, `$_ENV`)
2. First available `.env` fallback parsing from project paths (for non-Docker Apache runs)
3. `settings.ini` (`page` and `settings` sections)
4. Hard-coded defaults

Supported variables:

- `PAGE_TITLE`
- `PAGE_DESCRIPTION`
- `STYLESHEET` (file under `src/assets/`)
- `COMPANY_NAME`
- `COMPANY_LOGO` (file under `src/assets/`)
- `COMPANY_URL`
- `CONTACT_EMAIL`
- `TINYMCE_API_KEY`
- `CLOUDFLARE_TUNNEL_TOKEN` (used by the compose tunnel service)

TinyMCE behavior:

- The page loads Tiny Cloud using `TINYMCE_API_KEY`.
- If TinyMCE fails to load, the app still works with the plain textarea.

## Repository structure

- `compose.yml` - Docker services (`php`, optional `cloudflare-tunnel`)
- `openapi.yaml` - API spec for core endpoints (`entries`, `tags`)
- `Dockerfile` - PHP Apache image
- `php.ini` - runtime PHP settings (upload size, memory, errors)
- `src/index.php` - UI page and config loading
- `src/api/entries.php` - create/list entries
- `src/api/tags.php` - list tags with counts/colors
- `src/api/submitters.php` - list submitters with counts
- `src/api/uploads.php` - image upload endpoint
- `src/assets/app.js` - frontend logic (editor, sanitize, filters)
- `src/assets/styles.css` - UI styling
- `src/data/` - runtime data (`changelog.db`, `tags.csv`, `submitters.csv`, `uploads/`)

## API

All endpoints return JSON.

### `POST /api/entries.php`

Create an entry.

- Accepts JSON body or form fields
- Required: `title`
- Optional: `description`, `submitter`, `tags` (array or comma-separated), `timestamp` (epoch ms or parseable datetime)
- Returns created entry with `id` and `timestamp`

### `GET /api/entries.php`

List entries with filters.

Query params:

- `from`, `to` - datetime/epoch filter
- `submitter` - partial match
- `tags` - comma-separated; each tag must be present
- `sort` - `timestamp` (default), `submitter`, `tags`, `title`
- `order` - `desc` (default) or `asc`
- `limit` - optional max rows per response (capped at `200`)
- `offset` - optional starting row offset (used for pagination)

### `GET /api/tags.php`

Returns tag objects with usage counts, merged from:

- `src/data/tags.csv` (or fallback path)
- tags found in `entries` table

Shape:

```json
[
  { "tag": "Bug", "hex": "FF0000", "count": 12 }
]
```

### `GET /api/submitters.php`

Returns submitter objects with usage counts, merged from:

- `src/data/submitters.csv` (or fallback path)
- submitters found in `entries` table

Shape:

```json
[
  { "submitter": "Alice", "count": 7 }
]
```

### `POST /api/uploads.php`

Uploads an image file (`multipart/form-data`, field name: `file`).

- Methods other than `POST` return `405`
- Max size: 10 MB
- Allowed MIME types: PNG, JPEG, GIF, WebP
- Stores files under `src/data/uploads/`
- Returns `201` with:

```json
{
  "location": "/data/uploads/<generated-file>",
  "width": 1200,
  "height": 800
}
```

## OpenAPI

`openapi.yaml` currently documents:

- `GET/POST /api/entries.php`
- `GET /api/tags.php`

It does not yet include `submitters` or `uploads`.

## Data and storage notes

- SQLite database path: `src/data/changelog.db`
- Table: `entries(id, title, description, submitter, tags, timestamp)`
- Tags are stored as a comma-wrapped CSV string (example: `,bug,ui,`)
- `timestamp` is stored as epoch milliseconds

## Development notes

- Requires PHP with `pdo` and `pdo_sqlite`
- `php.ini` sets `upload_max_filesize=10M` and `post_max_size=12M`
- In compose, `src` is bind-mounted to `/var/www/html`

## Security notes

- No authentication or authorization is built in
- No CORS policy headers are set by default
- Rich text is sanitized client-side before submit; add server-side sanitization if you expose this publicly
- Uploaded images are validated by MIME type and image parsing before being stored

## License

This project is licensed under **The Creator's License**.

See [LICENSE.md](LICENSE.md) for the full license text and terms. They are most certainly different than what you're used to, so please read them carefully.

## Acknowledgments

- [Erisa from Cloudflare Community](https://community.cloudflare.com/t/can-i-use-cloudflared-in-a-docker-compose-yml/407168) for the Cloudflare Tunnel guidance.
