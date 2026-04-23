# In: nuestrachante/prod/backend

php artisan down

# Wipe EVERYTHING (nuclear option - be 100% sure you want this!)
mysql -u virozs5_x4nt3_usr -p']=H*BRt%X]59.L$;' virozs5_x4nt3 <<'EOF'
SET FOREIGN_KEY_CHECKS=0;
SET @tables = NULL;
SELECT GROUP_CONCAT(CONCAT('`', table_name, '`')) INTO @tables
FROM information_schema.tables
WHERE table_schema = DATABASE();
SET @tables = IFNULL(@tables, '');
SET @sql = IF(@tables = '', 'SELECT 1', CONCAT('DROP TABLE ', @tables));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET FOREIGN_KEY_CHECKS=1;
EOF

# Now run fresh migrations
php artisan migrate --force

# Seed root user
php artisan db:seed --force --class='Database\Seeders\RootUserSeeder'

# Clear/cache
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan up