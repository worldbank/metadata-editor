# Windows Service for Metadata Editor Worker

Runs the job-queue worker as a native Windows service via [NSSM](https://nssm.cc) —
the Windows equivalent of the Linux systemd setup in `deploy/linux/`.

## Prerequisites

1. **PHP** installed and accessible (either on `PATH` or note the full path to `php.exe`).
2. **NSSM** downloaded from <https://nssm.cc/download> and placed on `PATH`
   (e.g. copy `nssm.exe` to `C:\Windows\System32\`) or note the full path.

## Quick setup

1. **Open an elevated PowerShell** (Run as Administrator).

2. **Run the installer** from the `deploy\windows\` directory:

   ```powershell
   .\install-service.ps1
   ```

   The defaults assume the app lives at `C:\inetpub\metadata-editor`.
   Override with parameters as needed:

   ```powershell
   .\install-service.ps1 -AppRoot "D:\www\metadata-editor" -MaxJobs 100
   ```

3. **Verify it is running:**

   ```powershell
   nssm status metadata-editor-worker
   # or
   Get-Service metadata-editor-worker
   ```

## Parameters

| Parameter | Default | Description |
|---|---|---|
| `-AppRoot` | `C:\inetpub\metadata-editor` | Directory containing `index.php` |
| `-PhpExe` | `php` | Path to `php.exe` (or name if on PATH) |
| `-NssmExe` | `nssm` | Path to `nssm.exe` (or name if on PATH) |
| `-MaxJobs` | `50` | Worker exits after N jobs; NSSM restarts it |
| `-ServiceUser` | *(LocalSystem)* | Service account (e.g. `.\IIS_AppPool`) |
| `-ServicePassword` | *(blank)* | Password for `-ServiceUser` |

Example with custom PHP path and a dedicated service account:

```powershell
.\install-service.ps1 `
    -AppRoot      "D:\www\metadata-editor" `
    -PhpExe       "C:\php\php.exe" `
    -NssmExe      "C:\tools\nssm\nssm.exe" `
    -MaxJobs      100 `
    -ServiceUser  "DOMAIN\svc_worker" `
    -ServicePassword "s3cr3t"
```

## Restart after N jobs (memory leak prevention)

The worker is started with `--max-jobs=N` (default 50). After processing that many
jobs it exits with code 0. NSSM detects the exit and restarts it after 5 seconds,
giving you a fresh PHP process and avoiding long-lived memory growth.

- **Change the limit:** re-run the installer with `-MaxJobs 200`, or use:
  ```powershell
  nssm set metadata-editor-worker AppParameters "index.php cli/worker/run --max-jobs=200"
  nssm restart metadata-editor-worker
  ```
- **Disable the limit:** remove `--max-jobs` from `AppParameters` (worker runs until crash or manual stop).

## Useful commands

| Command | Description |
|---|---|
| `nssm status metadata-editor-worker` | Show current state |
| `nssm start metadata-editor-worker` | Start the service |
| `nssm stop metadata-editor-worker` | Stop the service |
| `nssm restart metadata-editor-worker` | Restart the service |
| `nssm edit metadata-editor-worker` | Open NSSM GUI editor |
| `nssm remove metadata-editor-worker confirm` | Uninstall the service |
| `Get-Content logs\worker.log -Tail 50 -Wait` | Follow the log |

You can also manage the service from **services.msc** — look for `metadata-editor-worker`.

## Logs

Worker output (stdout + stderr) is appended to `<AppRoot>\logs\worker.log`.
The log directory is created automatically by the installer.

For production, consider rotating this file with a tool such as
[logrotate for Windows](https://github.com/plecos/logrotate-win) or a scheduled PowerShell task.
