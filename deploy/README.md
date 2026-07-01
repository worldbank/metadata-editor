# Deploy: Metadata Editor Worker Service

Install the background job-queue worker as a system service on your platform:

| Platform | Directory | Installer |
|---|---|---|
| Linux (systemd) | [`linux/`](linux/) | `sudo ./install-service.sh` |
| Windows (NSSM) | [`windows/`](windows/) | `.\install-service.ps1` |

See the README in each directory for prerequisites, parameters, and troubleshooting.
