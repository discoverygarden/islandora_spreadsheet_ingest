#!/bin/bash

# Helper script, to copy our fixtures where expected by migration_example.csv

FIXTURES=${1:-$(drush drupal:directory $MODULE)/fixtures}
DEST_LINK=${2:-$(drush drupal:directory files)/isifixturefiles}
APACHE_USER=${3:-www-data}

sudo -u $APACHE_USER -- rsync -av $FIXTURES $DEST_LINK
