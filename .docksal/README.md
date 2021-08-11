# Generic Drupal Docksal setup with Pantheon integration
This provides Docksal-based Drupal tooling with a method for retrieving content from a specific Pantheon site. This branch is usable with Drupal 8/9/+ sites. For Drupal 7 sites, use the [7.x branch](https://github.austin.utexas.edu/eis1-wcs/pantheon_docksal_starter/tree/7.x)

1. If you have not yet done so, add a Pantheon terminus machine token and filename of SSH
key to `$HOME/.docksal/docksal.env` in the following format:

```bash
# Pantheon machine token value. Should be a long random string.
# Either copy from the `token` value of `$HOME/.terminus/cache/tokens/<YOUR_EID>@eid.utexas.edu`
# or generate a new machine token, see docs at https://pantheon.io/docs/machine-tokens
SECRET_TERMINUS_TOKEN=""

# Pantheon SSH PRIVATE Key https://docs.docksal.io/core/system-ssh-agent/
# (e.g., SECRET_SSH_KEY_PANTHEON="id_rsa")
# This should be the filename of the *private* key used to authenticate
# to Pantheon from your local machine. In most cases, should be `id_rsa`.
SECRET_SSH_KEY_PANTHEON=""
```

2. Copy the contents of this repository into a `.docksal` directory at the
project root of your site repository.

```
git clone git@github.austin.utexas.edu:eis1-wcs/pantheon_docksal_starter.git .docksal && rm -rf .docksal/.git
```

3. Configure this environment for use with a Pantheon site:

```
fin init
fin config set HOSTING_SITE="[Pantheon site machine name]"
```

## Common commands

- `fin init`: Required setup for all work. Adds a local-settings.php file that provides the database connection and spins up all needed containers
- `fin pull db -y` (optional `--hosting-env=`): Pull the database from the `live` environment unless otherwise specified. From the Docksal docs: "When pulling the database, it stays cached within your cli container for a period of one (1) hour. If at any point this needs to be updated, use the --force option as this will bypass the database and reimport."
- `fin pull files` (optional `--hosting-env=`): Rsyncs files from the `live` environment (unless otherwise specified) to your local files directory
- `fin cr`: clear the Drupal 8+ cache quickly
- `fin uli` Get a one-time sign-in link with the hostname.

## Troubleshooting

### The site homepage loads but all other pages 404
This Docksal stack currently uses Apache, not Nginx (like Pantheon). The
codebase is likely missing an `.htaccess` file.

### No Drush commands work / drush st cannot find the database
Add `drush/drush` as a composer requirement. If drush still cannot find the database, make sure that your `settings.local.php` file contains the Docksal
database connection shown in `default.settings.local.php`.

### The SimpleSAML library throws notices & errors
1. Add to your `settings.local.php` file: `$config['simplesamlphp_auth.settings']['activate'] = 0;`
2. Conditionally load the `settings.pantheon.saml.php` file:

```
/**
 * If there is a saml settings file, then include it.
 */
$pantheon_saml_settings = __DIR__ . "/settings.pantheon.saml.php";
if (file_exists($pantheon_saml_settings) && !file_exists($local_settings)) {
  include $pantheon_saml_settings;
}
```

### Some assets in sites/default/files are there after `fin pull files` but don't seem to load
The `.htaccess` file in `sites/default/files` that normally has no effect on Pantheon (nginx) is now having an effect. Various solutions are possible:

- Turn on `mod_rewrite`.
- Remove `sites/default/files/.htaccess`
- Change the permissions on the directories/files to `777`.
