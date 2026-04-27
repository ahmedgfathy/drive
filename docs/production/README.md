# Production Helpers

This folder contains the temporary helper scripts used during the `drive.pms.eg` production deployment and recovery work.

- `prod-remote.sh`: shared SSH helper for remote execution
- `prod-cutover.sh`: production cutover workflow
- `prod-fix-mysql-user.sh`: MySQL app-user repair helper
- `test-remote-sudo.sh`: sudo verification helper
- `deploy-from-github.sh`: production update script after new code is pushed to GitHub
- `install-queue-service.sh`: one-time installer for the Laravel queue worker systemd service
