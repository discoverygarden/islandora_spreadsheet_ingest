# Cheap script to facilitate dev.
drush migrate-reset-status islandora_spreadsheet_nodes_example &&\
drush migrate-reset-status islandora_spreadsheet_media_example &&\
drush migrate-reset-status islandora_spreadsheet_files_example &&\
# Known issue with content sync causes OOM. Disable the context config.
drush migrate-rollback -v --group=islandora_spreadsheet_example &&\
drush pm-uninstall islandora_spreadsheet_ingest &&\
drush en islandora_spreadsheet_ingest &&\
drush cr &&\
drush-test-no-empty islandora_spreadsheet_ingest &&\
drush migrate-reset-status islandora_spreadsheet_nodes_example &&\
drush migrate-reset-status islandora_spreadsheet_media_example &&\
drush migrate-reset-status islandora_spreadsheet_files_example &&\
drush migrate:batch-import -v --execute-dependencies --group=islandora_spreadsheet_example ;\
echo Files &&
drush migrate-messages islandora_spreadsheet_files_example &&\
echo Media &&
drush migrate-messages islandora_spreadsheet_media_example &&\
echo Nodes &&
drush migrate-messages islandora_spreadsheet_nodes_example
