# Docker Compose Development

This setup runs the Metadata Editor locally with PHP/Apache, a PHP queue worker,
MySQL, Mailpit, and the companion FastAPI data-processing service.

## Prerequisites

- Docker Compose v2.
- The FastAPI service (https://github.com/worldbank/metadata-editor-fastapi) checked out next to this repository at
  `../metadata-editor-fastapi`, or set `FASTAPI_CONTEXT` in `.env`.

## FastAPI Build Note

- The companion `metadata-editor-fastapi` repository currently does not include
  a top-level `Dockerfile`.
- This project provides an inline Dockerfile in `docker-compose.yml` for the
  `fastapi` service build.
- If you see `failed to read dockerfile: open Dockerfile: no such file or
  directory`, verify that `FASTAPI_CONTEXT` points to your cloned
  `metadata-editor-fastapi` path.

## Run

```sh
docker compose up --build
```

Open:

- App: http://localhost:8080
- Mailpit: http://localhost:8025
- FastAPI: http://localhost:8000
- MySQL from host: `localhost:3307`

On the first run, open http://localhost:8080/index.php/install and use the
installer to create the database tables and initial admin user.

## Notes

- PHP and FastAPI both mount `app-datafiles` at `/var/www/html/datafiles`.
  Keep that shared absolute path aligned because PHP sends `realpath(...)`
  file paths to FastAPI.
- Local database state is kept in the `db-data` volume. To reset everything:

```sh
docker compose down -v
```
