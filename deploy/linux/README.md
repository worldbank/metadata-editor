# Linux Systemd Service for Metadata Editor Worker

Runs the job-queue worker as a systemd service — the Linux equivalent of the
Windows NSSM setup in `deploy/windows/`.

## Quick setup

1. **Run the installer** from the `deploy/linux/` directory:

   ```bash
   sudo ./install-service.sh
   ```

   The defaults assume the app lives at `/var/www/metadata-editor` and runs as
   `www-data`. Override as needed:

   ```bash
   sudo ./install-service.sh \
       --app-root /srv/metadata-editor \
       --user nginx \
       --group nginx \
       --max-jobs 100
   ```

2. **Check status**

   ```bash
   sudo systemctl status metadata-editor-worker
   journalctl -u metadata-editor-worker -f
   ```

## Parameters

| Option | Default | Description |
|---|---|---|
| `--app-root` | `/var/www/metadata-editor` | Directory containing `index.php` and `worker.sh` |
| `--user` | `www-data` | User to run the service as |
| `--group` | `www-data` | Group to run the service as |
| `--max-jobs` | `50` | Worker exits after N jobs; systemd restarts it |

## Manual setup

If you prefer to install without the script:

1. **Copy the service file**
   ```bash
   sudo cp metadata-editor-worker.service /etc/systemd/system/
   ```

2. **Create an override** so the service uses your app path and user:
   ```bash
   sudo mkdir -p /etc/systemd/system/metadata-editor-worker.service.d
   sudo tee /etc/systemd/system/metadata-editor-worker.service.d/override.conf << 'EOF'
   [Service]
   Environment="APP_ROOT=/var/www/metadata-editor"
   Environment="WORKER_MAX_JOBS=50"
   User=www-data
   Group=www-data
   EOF
   ```
   Replace `APP_ROOT` with the real path to your app (where `worker.sh` and `index.php` live).  
   Replace `User`/`Group` with the user that should run the worker (e.g. your web server user).

3. **Reload and enable**
   ```bash
   sudo systemctl daemon-reload
   sudo systemctl enable --now metadata-editor-worker
   ```

## Restart after N jobs (memory leak prevention)

The worker is started with `--max-jobs=N` (default 50). After processing that many jobs, it exits with code 0. systemd then restarts it (`Restart=always`), so you get a fresh process and avoid long-lived memory leaks.

- Change the limit: set `WORKER_MAX_JOBS` in the override (e.g. `Environment="WORKER_MAX_JOBS=1000"`).
- Disable the limit: remove `--max-jobs` from `ExecStart` in an override (worker runs until crash or stop).

## Useful commands

| Command | Description |
|--------|-------------|
| `systemctl status metadata-editor-worker` | Show status |
| `systemctl restart metadata-editor-worker` | Restart now |
| `systemctl stop metadata-editor-worker` | Stop |
| `journalctl -u metadata-editor-worker -f` | Follow logs |
| `journalctl -u metadata-editor-worker -n 100` | Last 100 lines |
