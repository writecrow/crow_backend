# Basecamp API v3 Integration
This is a Drupal module that facilitates using the [https://github.com/basecamp/bc3-api](Basecamp API v3). 

As an integration module, this facilitates transactions between the Basecamp endpoints, and requires simple coding to achieve this.

After creating an authorized application & storing the initial access token & refresh token, this module will continue to renew the token
via a cron job that runs once per day.

Once complete, actions, such as creating a new todo, are as simple as:

```php
$data = [
  'content' => $title,
  'description' => $message,
  'due_on' => date('Y-m-d', strtotime('+7 days')),
  'notify' => TRUE,
  'assignee_ids' => [1,2,3],
];
$project = $config->get('project');
$list = $config->get('list');
  
Basecamp::createTodo($project, $list, $data);
```


## Proper setup & configuration of the refresh token
At this time, creating the initial access & refresh token is the purview of the developer (and it is pretty easy -- see https://github.com/basecamp/api/blob/master/sections/authentication.md).

1. Sign in to Basecamp with the user ID that will provide the integration (in most cases, this should be identified as a non-human account so that people know that actions performed are being triggered by the Drupal integration).
2. Go to https://launchpad.37signals.com/integrations and use the Authorization dialog to generate a 1-time code.
3. Trade this code for a [long-lived access token & refresh token](https://github.com/basecamp/api/blob/master/sections/authentication.md#oauth-2-from-scratch):

```bash
curl -X POST -d "type=web_server&client_id=your-client-id&redirect_uri=your-redirect-uri&client_secret=your-client-secret&code=verification-code" https://launchpad.37signals.com/authorization/token
```

4. Set these tokens in Drupal's non-config-exportable State API:

```
vendor/bin/drush state:set basecamp_api_refresh_token <your token>
vendor/bin/drush state:set basecamp_api_access_token <your token>
```

5. **Important**: by default, this token will not refresh via cron so that development environments don't accidentally invalidate your access token in the production environment. For the token to be refreshed regularly in your production environment, add the following to your `settings.php` or equivalent:

```
$settings['basecamp_api_do_refresh'] = TRUE;
```
