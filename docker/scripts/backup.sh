#!/bin/bash
# MySQL Backup Script for Production
# Automatically creates daily backups with rotation

set -e

# Configuration from environment variables
MYSQL_HOST="${MYSQL_HOST:-db}"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASSWORD="${MYSQL_PASSWORD}"
MYSQL_DATABASE="${MYSQL_DATABASE:-miboleta_prod}"
BACKUP_DIR="/backups"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-7}"

# Create backup directory if it doesn't exist
mkdir -p "${BACKUP_DIR}"

# Generate backup filename with timestamp
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/${MYSQL_DATABASE}_${TIMESTAMP}.sql.gz"

echo "==================================="
echo "Starting MySQL backup: ${TIMESTAMP}"
echo "Database: ${MYSQL_DATABASE}"
echo "==================================="

# Create backup
mysqldump \
    --host="${MYSQL_HOST}" \
    --user="${MYSQL_USER}" \
    --password="${MYSQL_PASSWORD}" \
    --single-transaction \
    --quick \
    --lock-tables=false \
    --routines \
    --triggers \
    --events \
    "${MYSQL_DATABASE}" | gzip > "${BACKUP_FILE}"

# Check if backup was successful
if [ $? -eq 0 ]; then
    echo "✓ Backup completed successfully: ${BACKUP_FILE}"

    # Get backup file size
    BACKUP_SIZE=$(du -h "${BACKUP_FILE}" | cut -f1)
    echo "  Size: ${BACKUP_SIZE}"
else
    echo "✗ Backup failed!"
    exit 1
fi

# Delete old backups (older than RETENTION_DAYS)
echo "==================================="
echo "Cleaning up old backups (older than ${RETENTION_DAYS} days)..."

find "${BACKUP_DIR}" -name "${MYSQL_DATABASE}_*.sql.gz" -type f -mtime +${RETENTION_DAYS} -delete

REMAINING_BACKUPS=$(find "${BACKUP_DIR}" -name "${MYSQL_DATABASE}_*.sql.gz" -type f | wc -l)
echo "✓ Remaining backups: ${REMAINING_BACKUPS}"

echo "==================================="
echo "Backup process completed!"
echo "==================================="
