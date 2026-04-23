# Database Persistence Guide

Your Docker setup uses a **named volume** (`pluribus_db_data`) which persists data across container restarts and rebuilds.

## ✅ Safe Commands (Data Persists)

```bash
# Restart containers (keeps all data)
docker compose restart

# Stop containers (keeps all data)
docker compose stop

# Stop and remove containers (keeps volumes/data)
docker compose down

# Rebuild and restart (keeps all data)
docker compose up -d --build

# View database container logs
docker compose logs db

# Access MySQL shell
docker compose exec db mysql -upluribus -ppluribus_secret pluribus
```

## ⚠️ Dangerous Commands (Will Delete Data)

```bash
# DON'T USE: Removes volumes (deletes database!)
docker compose down -v

# DON'T USE: Removes specific volume (deletes database!)
docker volume rm pluribus_db_data

# DON'T USE: Removes all unused volumes (may delete database!)
docker volume prune
```

## 🔍 Check Database Status

```bash
# List Docker volumes
docker volume ls | grep pluribus

# Inspect volume details (see creation date, size)
docker volume inspect pluribus_db_data

# Check if container is using correct volume
docker inspect pluribus-db-1 | grep -A 10 "Mounts"

# Connect to phpMyAdmin
# Open: http://localhost:9124
# User: pluribus
# Password: pluribus_secret
```

## 🛠️ Backup & Restore

### Create Backup
```bash
# Export database to SQL file
docker compose exec db mysqldump -upluribus -ppluribus_secret pluribus > backup_$(date +%Y%m%d_%H%M%S).sql

# Or use phpMyAdmin: http://localhost:9124 → Export tab
```

### Restore Backup
```bash
# Import SQL file
docker compose exec -T db mysql -upluribus -ppluribus_secret pluribus < backup_YYYYMMDD_HHMMSS.sql

# Or use phpMyAdmin: http://localhost:9124 → Import tab
```

## 🐛 Troubleshooting

### Volume was deleted - how to recover?
1. The volume `pluribus_db_data` was created on **2026-04-22 at 19:50**
2. If deleted, data is gone (unless you have a backup)
3. Run migrations again: `docker compose exec api php artisan migrate:fresh --seed`

### Want a fresh database?
```bash
# Option 1: Remove volume and recreate
docker compose down -v
docker compose up -d
docker compose exec api php artisan migrate:fresh --seed

# Option 2: Drop tables via phpMyAdmin (http://localhost:9124)
```

### Database keeps getting wiped?
- Check if you're running `docker compose down -v` by mistake
- Check scripts or aliases in `.bashrc` / `.zshrc` that might include `-v`
- The volume persists between sessions; verify with: `docker volume ls | grep pluribus_db_data`

## 📊 Current Configuration

From `docker-compose.yml`:
```yaml
volumes:
  db_data:
    name: pluribus_db_data  # Named volume persists data
```

The database container mounts this volume to `/var/lib/mysql`, which contains all your MySQL data files.
