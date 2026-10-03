#!/bin/bash
# Ensure MariaDB is running
if ! pgrep -x "mariadbd" > /dev/null; then
    echo "Starting MariaDB service..."
    mariadbd --user=mysql &
    sleep 2
fi

# Ensure database schema is initialized and seeded
php config/init_db.php 2>/dev/null

echo "Starting Sikkim PHP Web Server on 0.0.0.0:3000..."
exec php -S 0.0.0.0:3000 router.php
