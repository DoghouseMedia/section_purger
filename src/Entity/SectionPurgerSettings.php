<?php

namespace Drupal\section_purger\Entity;

use Drupal\purge\Plugin\Purge\Purger\PurgerSettingsBase;
use Drupal\purge\Plugin\Purge\Purger\PurgerSettingsInterface;

/**
 * Defines the section purger settings entity.
 *
 * @ConfigEntityType(
 *   id = "sectionpurgersettings",
 *   label = @Translation("Section"),
 *   config_prefix = "settings",
 *   static_cache = TRUE,
 *   entity_keys = {"id" = "id"},
 *   config_export = {
 *     "account",
 *     "application",
 *     "body",
 *     "bodyContentType",
 *     "connectTimeout",
 *     "cooldownTime",
 *     "description",
 *     "environmentname",
 *     "headers",
 *     "hostname",
 *     "httpErrors",
 *     "id",
 *     "invalidationtype",
 *     "label",
 *     "maxRequests",
 *     "name",
 *     "password",
 *     "path",
 *     "port",
 *     "requestMethod",
 *     "runtimeMeasurement",
 *     "scheme",
 *     "sitename",
 *     "timeout",
 *     "username",
 *     "varnishname",
 *     "verify"
 *   },
 * )
 */
class SectionPurgerSettings extends PurgerSettingsBase implements PurgerSettingsInterface {

  /**
   * Instance metadata.
   */

  /**
   * The readable name of this purger.
   *
   * @var string
   */
  public $name = '';

  /**
   * The invalidation plugin ID that this purger invalidates.
   *
   * @var string
   */
  public $invalidationtype = 'tag';

  /**
   * Primary request information.
   */

  /**
   * The hostname of the Drupal site to purge.
   *
   * @var string
   */
  public $sitename = '';

  /**
   * The name of the varnish proxy instance (defined in section config)
   *
   * @var string
   */
  public $varnishname = 'varnish';

  /**
   * The host or IP-address to connect to.
   *
   * @var string
   */
  public $hostname = 'aperture.section.io';

  /**
   * The port to connect to.
   *
   * @var int
   */
  public $port = 443;

  /**
   * Section account ID.
   *
   * @var int
   */
  public $account = 1;

  /**
   * Section application ID.
   *
   * @var int
   */
  public $application = 100;

  /**
   * Section environment name i.e. Production, Staging, etc.
   *
   * @var string
   */
  public $environmentname = 'Production';

  /**
   * Section Username.
   *
   * @var string
   */
  public $username = 'username';

  /**
   * Section Password.
   *
   * @var string
   */
  public $password = 'password';

  /**
   * The HTTP path.
   *
   * @var string
   */
  public $path = '/';

  /**
   * The HTTP request method.
   *
   * @var string
   */
  public $requestMethod = 'POST';

  /**
   * The HTTP scheme.
   *
   * @var string
   */
  public $scheme = 'https';

  /**
   * Whether to verify SSL certificates or not.
   *
   * @var bool
   *
   * @see http://docs.guzzlephp.org/en/latest/request-options.html#verify
   */
  public $verify = TRUE;

  /**
   * Request headers (outbound).
   */

  /**
   * Configured outgoing HTTP headers.
   *
   * @var array[]
   */
  public $headers = [];

  /**
   * Body (request payload).
   */

  /**
   * The body payload to send.
   *
   * @var string
   */
  public $body = '';

  /**
   * The content-type of the body payload being sent.
   *
   * @var string
   */
  public $bodyContentType = 'application/json';

  /**
   * Performance settings.
   */

  /**
   * Runtime measurement.
   *
   * When FALSE, dynamic capacity calculation will be disabled and based upon
   * the connectTimeout and timeout settings.
   *
   * @var bool
   */
  public $runtimeMeasurement = TRUE;

  /**
   * The timeout of the request in seconds.
   *
   * @var float
   */
  public $timeout = 1.0;

  /**
   * The number of seconds to wait while trying to connect to a server.
   *
   * @var float
   */
  public $connectTimeout = 1.0;

  /**
   * Cooldown time.
   *
   * Number of seconds to wait after one or more invalidations took place (so
   * that other purgers get fresh content).'
   *
   * @var float
   */
  public $cooldownTime = 0.0;

  /**
   * Maximum requests.
   *
   * Maximum number of HTTP requests that can be made during Drupal's execution
   * lifetime. Usually PHP resource restraints lower this value dynamically, but
   * can be met at the CLI.
   *
   * @var int
   */
  public $maxRequests = 250;

  /**
   * Success resolution.
   */

  /**
   * Whether 4xx and 5xx responses need to be treated as failures or not.
   *
   * @var bool
   *
   * @see http://docs.guzzlephp.org/en/latest/request-options.html#http-errors
   */
  public $httpErrors = TRUE;

}
