#!/bin/bash
TABLES=$(mysql --user=drupal8 --password=drupal8 --database=drupal8 --host=database --port=3306 -e 'SHOW TABLES LIKE "cache%"' | awk '{ print $1}' | grep -v '^Tables' ) || true
echo "Dropping cache tables... "
for t in $TABLES; do
  echo "Dropping $t table from lando database..."
  mysql --user=drupal8 --password=drupal8 --database=drupal8 --host=database --port=3306 -e "DROP TABLE $t"
done
rm -f /app/web/sites/default/files/css/*.css
rm -f /app/web/sites/default/files/js/*.js
rm -f /app/web/sites/default/files/css/*.css.gz
rm -f /app/web/sites/default/files/js/*.js.gz
echo "Cache rebuild complete."
