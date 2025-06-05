## Feedback Endpoint
This module provides a REST resource endpoint for receiving a POST request and sending an email with the content provided. The example in this module demonstrates feedback form content, but the `SubmitIssue` endpoint can serve as a model for other types of endpoints.

## Recommended setup
1. Use the `simple_oauth` module
2. Enable Drupal core's `rest_ui` module.
3. Go to `/admin/config/services/rest` and enable the "Submit an issue" endpoint:
    - Granularity: Resource
    - Method: POST
    - Accepted request formats: json, xml
    - Authentication providers: oauth2
4. Set permission for which role(s) may access the endpoint at `/admin/people/permissions#module-rest`
5. Ensure that your endpoint can be reached by configuring your `services.yml` file:

```
cors.config:
  enabled: true
  # Specify allowed headers, like 'x-allowed-header'.
  allowedHeaders: ['x-csrf-token','authorization','content-type','accept','origin','x-requested-with', 'access-control-allow-origin','x-allowed-header']
  # Specify allowed request methods, specify ['*'] to allow all possible ones.
  allowedMethods: ['GET', 'POST']
  # Configure requests allowed from specific origins (ideally, limit this to the expected origins)
  allowedOrigins: ['*']
  # Sets the Access-Control-Expose-Headers header.
  exposedHeaders: false
  # Sets the Access-Control-Max-Age header.
  maxAge: false
  # Sets the Access-Control-Allow-Credentials header.
  supportsCredentials: false
```

If replicating the original demonstration `SubmitIssue`, pay close attention to the annotation, which defines the
route, and must follow an idiosyncratic format to work with POST requests (see https://www.drupal.org/forum/support/post-installation/2017-02-21/post-return-no-route-found-error-while-get-request-is):

```
 * @RestResource(
 *   id = "feedback_endpoint_bug_report",
 *   label = @Translation("Submit an issue"),
 *   uri_paths = {
 *    "canonical" = "/submit-issue",
 *    "create" = "/submit-issue"
 *   }
 * )
 ```
