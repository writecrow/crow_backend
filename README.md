# Corpus/Repository Backend

[![Drupal 8 site](https://img.shields.io/badge/drupal-8-blue.svg)](https://drupal.org)

## Overview
This project contains the canonical resources to build the backend for a
corpus/repository management framework which serves data over a REST API. This is built on the Drupal CMS, following conventions of [Entity API](https://www.drupal.org/docs/8/api/entity-api/introduction-to-entity-api-in-drupal-8), [Search API](https://www.drupal.org/project/search_api), and the [REST API](https://www.drupal.org/docs/8/api/restful-web-services-api/restful-web-services-api-overview), and its configuration/implementation should present no surprises for developers familiar with Drupal.

From a fresh installation, the database schema will provide a `text` entity type, which holds the corpus text data and metadata, and a `repository` entity type, which references materials related to the texts. These entity types and the metadata they contain can be modified or extended as needed to fit the individual corpus.

The configuration provided subsequently includes search indices for texts and repository materials, and a REST API for performing keyword or metadata searches against the dataset.

This codebase does not make any assumptions about the way the data provided by the API is displayed (in a frontend).

## Building the codebase
Developing your own version of this site assumes familiarity with, and local installation of, the [Composer(https://getcomposer.org/) package manager. This repository contains only the "kernel" of the customized code & configuration. It uses Composer to build all assets required for the site, including the Drupal codebase and a handful of corpus-related PHP libraries.

Run `composer install` from the document root. This will build all assets required for the site. That's it!

## Installing the site
The following assumes familiarity with local web development for a PHP/MySQL stack. Since Drupal is written in PHP and uses an SQL database, that means you'll need:
- PHP 5.5.9 or higher. See [Drupal 8 PHP versions supported](https://www.drupal.org/docs/8/system-requirements/drupal-8-php-requirements).
- A database server (MySQL, PostgreSQL, or SQLlite that meets the [minimum Drupal 8 requirements](https://www.drupal.org/docs/8/system-requirements/database-server)).
- A webserver that meets the minimum PHP requirements above. Typically, this means Apache, Nginx, or Microsoft IIS. See [Drupal webserver requirements](https://www.drupal.org/docs/8/system-requirements/web-server).

There are a number of pre-packaged solutions that simplify setup of the above. These includes [MAMP](https://www.mamp.info/en/), [Valet](https://laravel.com/docs/5.6/valet), and [Lando](https://docs.devwithlando.io/).

1. `cp sites/example.settings.local.php sites/default/settings.local.php`
2. Create a MySQL database, then add its connection credentials to the newly created `settings.local.php`. Example:

```php
$databases['default']['default'] = [
  'database' => 'MYSQL_DATABASE',
  'username' => 'MYSQL_USERNAME',
  'password' => 'MYSQL_PASSWORD',
  'host' => 'localhost',
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];
```
3. Either navigate to your local site's domain and follow the web-based installation instructions, or if you prefer to use `drush`, run the drush [site-install](https://drushcommands.com/drush-8x/core/site-install/) command.
4. That's it! After signing in at `/user`, you should see the two available entity types at `/node/add`, the available metadata references at `/admin/structure/taxonomy` and the search configuration at `/admin/config/search/search-api`

## Importing data
Properly prepared text files can be imported via a drag-and-drop interface at `/admin/config/media/import`

Each text file needs to include the metadata elements in the file, followed by the actual text to be indexed. A model for that file structure is below:

```
<ID: 11165>
<Country: BGD>
<Assignment: 1>
<Draft: A>
<Semester in School: 2>
<Gender: M>
<Term writing: Fall 2015>
<College: E>
<Program: Engineering First Year>
<TOEFL-total: NA>
<TOEFL-reading: NA>
<TOEFL-listening: NA>
<TOEFL-speaking: NA>
<TOEFL-writing: NA>
Sed ut perspiciatis unde omnis iste natus error sit.

Voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur?
```
Alternative to the UI import, a directory of local text files can be imported via the drush `corpus-import` command. Example usage:

`drush corpus-import /Users/me/myfiles/`

## Performing search requests via the API
All API requests require basic HTTP authorization. Contact the corpus maintainers for access.

All endpoints are accessible via https and are located at `writecrow.corporaproject.org`, and can return data in either XML or JSON format. An example request for all texts matching a given ID, in JSON format, would look like this:

```
https://api.writecrow.org/texts/id?id=10533&_format=json
```

### All texts matching a given ID (& Assignment)
| Pattern | /texts/id?id=`ID`&assigment=`ASSIGNMENT` | 
| ------- | :---------------------------------------- |
| Example 1 |`/texts/id?id=10533&_format=json` | 
| Example 2 |`/texts/id?id=10533&assignment=2&_format=xml` | 
#### Sample output
```
[
  {"id":"10389","filename":"2_D_KOR_3_M_10389","draft":"D"},
  {"id":"10389","filename":"2_E_KOR_3_M_10389","draft":"E"},
  {"id":"10389","filename":"2_F_KOR_3_M_10389","draft":"F"}
 ]
```

### Single text matching a given filename
| Pattern | /texts/filename?filename=`FILENAME` | 
| ------- | :------------ |
| Example |`/texts/filename?filename=1_C_CHN_1_M_10285&_format=json` | 
#### Sample output
```
[{
  "filename":"1_C_CHN_1_M_10285",
  "assignment":"1",
  "college":"S",
  "country":"China",
  "draft":"C",
  "gender":"M",
  "id":"10285",
  "program":"Computer Science-BS",
  "semester":"1",
  "term":"Spring 2015",
  "toefl_listening":"26",
  "toefl_reading":"23",
  "toefl_speaking":"22",
  "toefl_writing":"25",
  "toefl_total":"96",
  "text":Lorem ipsum dolor sit amet..."
}]
```

### Text search using regular keyword(s)
| Pattern | /texts/keyword?keywords=`WORD+WORD` | 
| ------- | :------------ |
| Single keyword | `/texts/keyword?keywords=tassets&_format=json` |
| Multipe keywords, AND operator | `/texts/keyword?keywords=tassets+burnished&op=and&_format=json` | 
#### Notes
- Boolean and/or operator may be supplied when searching for multiple keywords. In the absence of a specified parameter, an "OR" search is performed.
- Keywords are separated by a `+`
#### Sample output
```
{"search_results":[{
  "assignment":"4",
  "college":"A",
  "country":"China",
  "draft":"L",
  "filename":"4_L_CHN_1_F_10206",
  "gender":"F",
  "program":"Agricultural Mech-BS",
  "semester_in_school":"1",
  "term_writing":"Spring 2015",
  "toefl_listening":"28",
  "toefl_reading":"22",
  "toefl_speaking":"22",
  "toefl_total":"97",
  "toefl_writing":"25",
  "search_api_excerpt":"\u2026 or colleagues have to be convincing , more specific and \u003Cstrong\u003Eprofessional\u003C\/strong\u003E. In most cases, Marketing plans are written for \u2026 by attracting their attention as well as explain some \u003Cstrong\u003Eprofessional\u003C\/strong\u003E concepts specifically. Last but not least, by \u2026 majors because they have to be focus on explaining \u003Cstrong\u003Eprofessional\u003C\/strong\u003E concepts and definition in their fields instead \u2026"
}]}
```

### Text search using lemmatized keyword(s)
| Pattern | /texts/lemma?keywords=`WORD+WORD` | 
| ------- | :------------ |
| Example | `/texts/lemma?op=and&keywords=professional+concepts&_format=json` | 

#### Notes
- Keywords submitted will automatically be lemmatized
- Currently, lemma with part of speech tagging is not supported
- Boolean and/or operator may be supplied when searching for multiple keywords. In the absence of a specified parameter, an "OR" search is performed.
- Keywords are separated by a `+`
#### Sample output
```
{"search_results":[{
  "assignment":"4",
  "college":"A",
  "country":"China",
  "draft":"L",
  "filename":"4_L_CHN_1_F_10206",
  "gender":"F",
  "program":"Agricultural Mech-BS",
  "semester_in_school":"1",
  "term_writing":"Spring 2015",
  "toefl_listening":"28",
  "toefl_reading":"22",
  "toefl_speaking":"22",
  "toefl_total":"97",
  "toefl_writing":"25",
  "search_api_excerpt":"\u2026 or colleagues have to be convincing , more specific and \u003Cstrong\u003Eprofessional\u003C\/strong\u003E. In most cases, Marketing plans are written for \u2026 by attracting their attention as well as explain some \u003Cstrong\u003Eprofessional\u003C\/strong\u003E concepts specifically. Last but not least, by \u2026 majors because they have to be focus on explaining \u003Cstrong\u003Eprofessional\u003C\/strong\u003E concepts and definition in their fields instead \u2026"
}]}
```