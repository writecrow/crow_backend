# Corpus-tagged Text Importer

A PHP library for uploading files tagged with corpus metadata, and storing it in
a database entity.

## Import steps

1. Sync the live database locally (`pull-db-crow`)
1. Import the database (`lando db-import 2022-09-19.sql`)
1. If provided with a full set of institutional texts (common), delete existing texts from that institution (`drush corpus-wipe --institution="147"`; here, 147 is the University of Arizona)
1. Import the new dataset (`drush corpus-import /app/repository_import`)
1. Wipe old indexing: `drush cww` && `drush clw`
1. Rebuild the new index `drush cwc` && `drush clc`.
1. For the frontend, make sure the latest base data is present in `src/app/corpus/corpus-base.json` (retrieve from `/corpus_search`)
