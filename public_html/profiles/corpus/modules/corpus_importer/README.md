# Corpus-tagged Text Importer

A PHP library for uploading files tagged with corpus metadata, and storing it in
a database entity.

![Screenshot of Conversion](https://raw.githubusercontent.com/writecrow/corpus_importer/master/corpus_importer.png)


## Import steps

1. Sync the live database locally (`pull-db-crow`)
1. If provided with a full set of institutional texts (common), delete existing texts from that institution (`drush corpus-wipe --institution="147"`; here, 147 is the University of Arizona)
