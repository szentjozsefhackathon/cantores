# Database Backup with Restic to S3

Daily automated backups of the PostgreSQL database using [restic](https://restic.net/), stored in an S3 bucket. Because the bucket is **shared** with other backup processes, all restic operations use a dedicated path prefix (`/creshu-prod`) to keep repositories isolated.

---

## Overview

- **What is backed up:** PostgreSQL dump (`pg_dump`) from the running `database` container, plus the Laravel `storage` Docker volume
- **Where:** S3 bucket, under the `creshu-prod/` prefix
- **How often:** Daily via cron
- **Retention:** 7 daily, 4 weekly, 6 monthly (adjust as needed)
- **Encryption:** Restic encrypts all data at rest with a password you control

---

## Prerequisites

Install restic on the production server:

```bash
# Debian/Ubuntu
sudo apt install restic

# or download the latest binary directly
curl -L https://github.com/restic/restic/releases/latest/download/restic_linux_amd64.bz2 | bunzip2 -o /usr/local/bin/restic
chmod +x /usr/local/bin/restic
```

Verify:
```bash
restic version
```

---

## 1. Configure Environment

Create `/etc/restic/creshu-prod.env` (readable only by root):

```bash
sudo mkdir -p /etc/restic
sudo touch /etc/restic/creshu-prod.env
sudo chmod 600 /etc/restic/creshu-prod.env
```

Populate it — see the [sample env file](#sample-env-file) below.

```bash
sudo nano /etc/restic/creshu-prod.env
```

### Sample Env File

```env
# /etc/restic/creshu-prod.env

# --- S3 credentials ---
AWS_ACCESS_KEY_ID=YOUR_ACCESS_KEY_ID
AWS_SECRET_ACCESS_KEY=YOUR_SECRET_ACCESS_KEY

# --- Restic repository ---
# Use a dedicated prefix so this repo doesn't clash with other processes in the shared bucket.
RESTIC_REPOSITORY=s3:s3.amazonaws.com/YOUR_BUCKET_NAME/creshu-prod

# --- Restic encryption password ---
# Generate a strong password: openssl rand -base64 32
RESTIC_PASSWORD=YOUR_STRONG_RESTIC_PASSWORD

# --- Docker Compose config (adjust if deploy path differs) ---
COMPOSE_DIR=/home/deploy/creshu-prod
COMPOSE_FILE=docker-compose.prod.yml
POSTGRES_USER=cantor
POSTGRES_DB=cantores

# --- Healthchecks.io ---
# Create a check at https://hc-ping.com, set its period to 1 day and grace to 1 hour.
# Paste the ping URL here (include the UUID, no trailing slash).
HC_PING_URL=https://hc-ping.com/YOUR-UUID-HERE
```

> **Important:** Store `RESTIC_PASSWORD` in a password manager or secrets vault. Without it you cannot decrypt your backups.

---

## 2. Initialize the Restic Repository

This is a **one-time** step. It creates the restic metadata structure inside `s3://.../creshu-prod/`.

```bash
source /etc/restic/creshu-prod.env
restic init
```

Expected output:
```
created restic repository xxxx at s3:s3.amazonaws.com/YOUR_BUCKET/creshu-prod
...
```

---

## 3. Install the Backup Script

Create `/usr/local/bin/backup-creshu-prod.sh`:

```bash
sudo nano /usr/local/bin/backup-creshu-prod.sh
sudo chmod 750 /usr/local/bin/backup-creshu-prod.sh
```

```bash
#!/usr/bin/env bash
# /usr/local/bin/backup-creshu-prod.sh
# Daily backup: PostgreSQL dump + Laravel storage volume → restic → S3

set -euo pipefail

ENV_FILE="/etc/restic/creshu-prod.env"
BACKUP_TMP="/tmp/creshu-backup-$$"

# Load config
# shellcheck source=/etc/restic/creshu-prod.env
source "$ENV_FILE"

# Ping helper: silently curl, ignore errors so network blips don't abort the backup
hc_ping() {
    curl -fsSo /dev/null --retry 3 --max-time 10 "${HC_PING_URL}${1}" || true
}

cleanup() {
    local exit_code=$?
    rm -rf "$BACKUP_TMP"
    if [ $exit_code -ne 0 ]; then
        echo "[$(date -Iseconds)] Backup FAILED (exit $exit_code). Pinging /fail..."
        hc_ping /fail
    fi
}
trap cleanup EXIT

# Signal job start — healthchecks.io will alert if /start is never followed by success
hc_ping /start
echo "[$(date -Iseconds)] Starting backup..."

mkdir -p "$BACKUP_TMP"

# --- 1. PostgreSQL dump ---
echo "[$(date -Iseconds)] Dumping PostgreSQL..."
docker compose -f "$COMPOSE_DIR/$COMPOSE_FILE" \
    exec -T database \
    pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB" \
    | gzip > "$BACKUP_TMP/db.sql.gz"

echo "[$(date -Iseconds)] DB dump size: $(du -sh "$BACKUP_TMP/db.sql.gz" | cut -f1)"

# --- 2. Laravel storage volume ---
echo "[$(date -Iseconds)] Snapshotting storage volume..."
docker run --rm \
    -v creshu-prod_storage:/data:ro \
    -v "$BACKUP_TMP":/backup \
    alpine \
    tar czf /backup/storage.tar.gz -C /data .

echo "[$(date -Iseconds)] Storage snapshot size: $(du -sh "$BACKUP_TMP/storage.tar.gz" | cut -f1)"

# --- 3. Push to restic ---
echo "[$(date -Iseconds)] Sending to restic..."
restic backup "$BACKUP_TMP" \
    --tag creshu-prod \
    --tag postgres \
    --host "$(hostname)"

# --- 4. Apply retention policy ---
echo "[$(date -Iseconds)] Applying retention policy..."
restic forget \
    --keep-daily  7 \
    --keep-weekly  4 \
    --keep-monthly 6 \
    --prune \
    --tag creshu-prod

echo "[$(date -Iseconds)] Backup complete."

# Signal success — only reached if every step above succeeded
hc_ping ""
```

> The script uses the Docker Compose project name `creshu-prod` to reference the named storage volume (`creshu-prod_storage`). If your deploy path or project name differs, adjust `COMPOSE_DIR` and the volume name accordingly.

---

## 4. Schedule with Cron

```bash
sudo crontab -e
```

Add a daily run at 03:00 (server local time), logging output:

```cron
0 3 * * * /usr/local/bin/backup-creshu-prod.sh >> /var/log/creshu-backup.log 2>&1
```

Ensure the log file exists and is writable by root:

```bash
sudo touch /var/log/creshu-backup.log
```

---

## 5. Verify Backups

After the first run (or run manually to test):

```bash
source /etc/restic/creshu-prod.env

# List snapshots
restic snapshots

# Inspect latest snapshot contents
restic ls latest

# Verify data integrity (spot-check)
restic check

# Full data verification (slower, reads all pack files from S3)
restic check --read-data
```

---

## Restore Procedure

### Restore the database

```bash
source /etc/restic/creshu-prod.env

# Extract the latest db dump to /tmp/restore
restic restore latest --target /tmp/restore

# Decompress and import into the running container
gunzip -c /tmp/restore/tmp/creshu-backup-*/db.sql.gz \
    | docker compose -f "$COMPOSE_DIR/$COMPOSE_FILE" \
        exec -T database \
        psql -U cantor cantores
```

### Restore the storage volume

```bash
# Extract the storage archive
tar xzf /tmp/restore/tmp/creshu-backup-*/storage.tar.gz -C /tmp/storage-restore/

# Copy files into the running container (adjust destination as needed)
docker cp /tmp/storage-restore/. \
    "$(docker compose -f "$COMPOSE_DIR/$COMPOSE_FILE" ps -q app)":/var/www/html/storage/
```

---

## Healthchecks.io Setup

1. Go to [hc-ping.com](https://hc-ping.com) and create a new check.
2. Set **Period** to `1 day` and **Grace** to `1 hour` (gives the backup time to finish before alerting).
3. Under **Start/Stop Signals**, ensure **"Start" signal** is enabled — this lets healthchecks.io detect jobs that hang or never complete.
4. Copy the ping URL (e.g. `https://hc-ping.com/xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`) and paste it as `HC_PING_URL` in your env file.

The script sends three signals:

| Event | URL suffix | Meaning |
|---|---|---|
| Job started | `/start` | Resets the clock; missing success within grace → alert |
| Job succeeded | _(none)_ | All steps completed without error |
| Job failed | `/fail` | An error was caught; immediate alert |

---

## Security Notes

- `/etc/restic/creshu-prod.env` is mode `600` (root only). Never commit it to version control.
- The restic repository is **encrypted** with `RESTIC_PASSWORD`. Back this password up independently (password manager, printed copy in a safe).
- The S3 IAM user should have only the minimum required permissions on the backup bucket (e.g., `s3:PutObject`, `s3:GetObject`, `s3:ListBucket`, `s3:DeleteObject` scoped to the bucket).
- Because the S3 bucket is shared, each restic repository uses a distinct key prefix (`/creshu-prod`). Never run `restic prune` or `restic forget` without the `--tag creshu-prod` filter, or it may affect other repositories in the same bucket.

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| `repository does not exist` | `restic init` not yet run | Run `restic init` (see step 2) |
| `wrong password or no key found` | `RESTIC_PASSWORD` mismatch | Verify env file value |
| `docker: permission denied` | Script not run as root | Ensure cron runs as root (`sudo crontab -e`) |
| `No such service: database` | Wrong `COMPOSE_DIR` or `COMPOSE_FILE` | Verify the path in the env file |
| Empty snapshots / small size | `pg_dump` failed silently | Run script manually and check output |
