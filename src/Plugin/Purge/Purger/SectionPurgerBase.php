<?php

namespace Drupal\section_purger\Plugin\Purge\Purger;

use Drupal\Core\Utility\Token;
use Drupal\purge\Plugin\Purge\Invalidation\Exception\InvalidExpressionException;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\purge\Plugin\Purge\Purger\PurgerBase;
use Drupal\purge\Plugin\Purge\Purger\PurgerInterface;
use Drupal\section_purger\Entity\SectionPurgerSettings;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abstract base class for HTTP based configurable purgers.
 */
abstract class SectionPurgerBase extends PurgerBase implements PurgerInterface {

  /**
   * The Guzzle HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * The settings entity holding all configuration.
   *
   * @var \Drupal\section_purger\Entity\SectionPurgerSettings
   */
  protected $settings;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Constructs the HTTP purger.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   An HTTP client that can perform remote requests.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $http_client, Token $token) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->settings = SectionPurgerSettings::load($this->getId());
    $this->client = $http_client;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
    $configuration,
    $plugin_id,
    $plugin_definition,
    $container->get('http_client'),
    $container->get('token')
    );
  }

  /**
   * SendReq($invalidation,$uri,$opt) - Utilizes HTTP to avoid code repetition.
   *
   * @param Invalidation $invalidation
   *   The invalidation object.
   * @param string $uri
   *   The URL of the API endpoint.
   * @param array $opt
   *   Request options (ie headers)
   * @param string $exp
   *   The ban expression.
   */
  public function sendReq(Invalidation $invalidation, $uri, array $opt, $exp) {
    // The banExpression is sent as a parameter in the URL,
    // so things like ampersands, asterisks,
    // question marks, etc will break the parse.
    $exp = urlencode($exp);
    // Append the banExpression to the URL.
    $uri .= $exp;
    try {
      /* $response = $this->client->request($this->settings->requestMethod, $uri, $opt); */
      $invalidation->setState(InvalidationInterface::SUCCEEDED);
    }
    catch (ConnectException $e) {
      $invalidation->setState(InvalidationInterface::FAILED);
      // Usually timeouts or other connection issues.
      $this->logger->critical("http request for " . $uri . " responded with " . $e->getMessage());
    }
    catch (\Exception $e) {
      $invalidation->setState(InvalidationInterface::FAILED);
      // Log as much useful information as we can.
      $headers = $opt['headers'];
      unset($opt['headers']);
      $debug = json_encode(
          str_replace(
            "\n",
            ' ',
            [
              'uri' => $uri,
              'method' => $this->settings->requestMethod,
              'guzzle_opt' => $opt,
              'headers' => $headers,
            ]
        )
          );
      $this->logger->critical($e->getMessage() . " \ndata: " . $debug);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    SectionPurgerSettings::load($this->getId())->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function getCooldownTime() {
    return $this->settings->cooldownTime;
  }

  /**
   * {@inheritdoc}
   */
  public function getIdealConditionsLimit() {
    return $this->settings->maxRequests;
  }

  /**
   * Retrieve all configured headers that need to be set.
   *
   * @param array $token_data
   *   An array of keyed objects, to pass on to the token service.
   *
   * @return string[]
   *   Associative array with header values and field names in the key.
   */
  protected function getHeaders(array $token_data) {
    $headers = [];
    $headers['Content-Type'] = "application/json";
    $headers['Accept'] = "application/json";
    $headers['user-agent'] = 'Section Purge module for Drupal 8.';
    if (strlen($this->settings->body)) {
      $headers['content-type'] = $this->settings->bodyContentType;
    }
    foreach ($this->settings->headers as $header) {
      // According to https://tools.ietf.org/html/rfc2616#section-4.2, header
      // names are case-insensitive. Therefore, to aid easy overrides by end
      // users, we lower all header names so that no doubles are sent.
      $headers[strtolower($header['field'])] = $this->token->replace(
      $header['value'],
      $token_data
      );
    }
    return $headers;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    if ($this->settings->name) {
      return $this->settings->name;
    }
    else {
      return parent::getLabel();
    }
  }

  /**
   * Retrieve the Guzzle connection options to set.
   *
   * @param array $token_data
   *   An array of keyed objects, to pass on to the token service.
   *
   * @return mixed[]
   *   Associative array with option/value pairs.
   */
  protected function getOptions(array $token_data) {
    $opt = [
      'auth' => [
        $this->settings->username,
        \Drupal::service('key.repository')->getKey($this->settings->password)->getKeyValue(),
      ],
      'httpErrors' => $this->settings->httpErrors,
      'connectTimeout' => $this->settings->connectTimeout,
      'timeout' => $this->settings->timeout,
      'headers' => $this->getHeaders($token_data),
    ];
    /* the body is unused as everything is url encoded, so, i see no reason to
    include this, especially since the bundled purger does not combine bodies.
    if (strlen($this->settings->body)) {
    $opt['body'] = $this->token->replace($this->settings->body, $token_data);
    }
     */
    if ($this->settings->scheme === 'https') {
      $opt['verify'] = (bool) $this->settings->verify;
    }
    return $opt;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeHint() {
    // When runtime measurement is enabled, we just use the base implementation.
    if ($this->settings->runtimeMeasurement) {
      return parent::getTimeHint();
    }
    // Theoretically connection timeouts and general timeouts can add up, so
    // we add up our assumption of the worst possible time it takes as well.
    return $this->settings->connectTimeout + $this->settings->timeout;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypes() {
    return [
      "url",
      "wildcardurl",
      "tag",
      "everything",
      "wildcardpath",
      "regex",
      "path",
      "domain",
      "raw",
    ];
  }

  /**
   * Retrieve the URI to connect to.
   *
   * @param array $token_data
   *   An array of keyed objects, to pass on to the token service.
   *
   * @return string
   *   URL string representation.
   */
  protected function getUri(array $token_data) {
    return sprintf(
      '%s://%s:%s%sapi/v1/account/%s/application/%s/environment/%s/proxy/%s/state?banExpression=',
      $this->settings->scheme,
      $this->settings->hostname,
      $this->settings->port,
      $this->token->replace($this->settings->path, $token_data),
      $this->settings->account,
      $this->settings->application,
      $this->settings->environmentname,
      $this->settings->varnishname
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getSiteName() {
    return $this->settings->sitename;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRuntimeMeasurement() {
    return (bool) $this->settings->runtimeMeasurement;
  }

  /**
   * This will invalidate urls.
   *
   * InvalidateUrls(array $invalidations)
   * The protocol is required and this must contain the hostname,
   * the protocol, and path (if any)
   * The protocol is specific; for example if invalidating an http request,
   * the https equivalent will not be invalidated.
   * e.x.: https://example.com/favicon.ico .
   *
   * @param array $invalidations
   *   This takes in an array of Invalidation,
   *   processing them all in a loop, generally from the purge queue.
   */
  public function invalidateUrls(array $invalidations) {
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);
      $invalidation->validateExpression();
      $parse = parse_url($invalidation->getExpression());
      if (!$parse) {
        $invalidation->setState(InvalidationInterface::FAILED);
        throw new InvalidExpressionException('URL Invalidation failed with ' . $invalidation->getExpression());
      }
      // Sanitize the path.
      $patterns = [
      // Escape regex characters except *.
      // Replace * with .* (for actual Varnish regex)
        '/([[\]{}()+?".,\\^$|#])/', '/\*/',
      ];
      $replace = [
      // Escape regex characters except *.
      // Replace * with .* (for actual Varnish regex)
        '\\\$1', '.*',
      ];
      $exp = 'req.http.X-Forwarded-Proto == "' . $parse['scheme'] . '" && ' . '" && req.http.host == "' . $parse['host'] . '" && req.url ~ "^';
      $exp .= preg_replace($patterns, $replace, substr($parse['path'], 1) . $parse['query'] . $parse['fragment']);
      $exp .= '$"';
      $this->logger->debug("[URL] expression `" . $invalidation->getExpression() . "` was replaced to be: `" . $exp . "`");
      $this->sendReq($invalidation, $uri, $opt, $exp);
    }
  }

  /**
   * This will invalidate paths.
   *
   * InvalidatePaths(array $invalidations)
   * As per the purger module guidelines,
   * this should not start with a slash, and should not contain the hostname.
   * e.x.: favicon.ico .
   *
   * @param array $invalidations
   *   This takes in an array of Invalidation,
   *   processing them all in a loop, generally from the purge queue.
   */
  public function invalidatePaths(array $invalidations) {
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);

      // Sanitize the path, stripping of regex and escaping quotes.
      $patterns = [
        '/^\//',
      // Escape regex characters except *.
      // Replace * with .* (for actual Varnish regex)
        '/([[\]{}()+?.,\\^$|#])/', '/\*/',
      ];
      $replace = [
      // Escape regex characters except *.
      // Replace * with .* (for actual Varnish regex)
        '', '\\\$1', '.*',
      ];
      // Base varnish ban expression for paths.
      $exp = 'req.url ~ "^/';
      $exp .= preg_replace($patterns, $replace, $invalidation->getExpression()) . '$"';

      // Adds this at the end if this instance has a site name
      // in the configuration, for multi-site pages.
      // the ampersands are url encoded to be %26%26 in sendReq.
      if ($this->getSiteName()) {
        $exp .= ' && req.http.host == "' . $this->getSiteName() . '"';
      }
      $this->logger->debug("[PATH] expression `" . $invalidation->getExpression() . "` was replaced to be: `" . $exp . "`");
      $this->sendReq($invalidation, $uri, $opt, $exp);
    }
  }

  /**
   * This will invalidate a hostname.
   *
   * InvalidateDomain(array $invalidations)
   * This should not contain the protocol, simply the hostname.
   * e.x.: example.com.
   *
   * @param array $invalidations
   *   This takes in an array of Invalidation,
   *   processing them all in a loop, generally from the purge queue.
   */
  public function invalidateDomain(array $invalidations) {
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);
      // From VarnishManageController.ts in aperture.
      $exp = 'req.http.host == "' . $invalidation->getExpression() . '"';
      $this->logger->debug("[DOMAIN] expression `" . $invalidation->getExpression() . "` was replaced to be: `" . $exp . "`");
      $this->sendReq($invalidation, $uri, $opt, $exp);
    }
  }

  /**
   * This will invalidate urls.
   *
   * Since by default invalidateUrls() has the ability to handle wildcard urls,
   * this is just an alias.
   * This method is still necessary to exist because purge itself has certain
   * validations for each type.
   */
  public function invalidateWildcardUrls(array $invalidations) {
    $this->invalidateUrls($invalidations);
  }

  /**
   * This will invalidate paths.
   *
   * Since by default invalidatePaths() has the ability to
   * handle wildcard urls, this is just an alias.
   * This method is still necessary to exist because purge
   * itself has certain validations for each type.
   */
  public function invalidateWildcardPaths(array $invalidations) {
    $this->invalidatePaths($invalidations);
  }

  /**
   * This allows for raw varnish ban expressions.
   *
   * InvalidateRawExpression(array $invalidations)
   * E.x.: obj.status == "404" && req.url ~ node\/(?).* - would clear the
   * cache of 404'd nodes.
   *
   * @param array $invalidations
   *   This takes in an array of Invalidation, processing them all in a loop,
   *   generally from the purge queue.
   */
  public function invalidateRawExpression(array $invalidations) {
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);

      $this->logger->debug("[Raw] expression `" . $invalidation->getExpression() . "` processed. This is a raw ban expression and requires syntax such as `req.url ~ ` preceeding the regex for standard use. `%26%26 req.http.host == [site name from config]` will NOT be appended at the end regardless of whether or not the multisite name is specified.");
      $this->sendReq($invalidation, $uri, $opt, $invalidation->getExpression());
    }
  }

  /**
   * This allows for a regular expression match of a path.
   *
   * InvalidateRegex(array $invalidations)
   * e.x.: obj.status == "404" && req.url ~ "node\/(?).*"
   * - would clear the cache of 404'd nodes.
   *
   * @param array $invalidations
   *   This takes in an array of Invalidation,
   *   processing them all in a loop, generally from the purge queue.
   */
  public function invalidateRegex(array $invalidations) {
    foreach ($invalidations as $invalidation) {
      $invalidation->setState(InvalidationInterface::PROCESSING);
      $token_data = ['invalidation' => $invalidation];
      $uri = $this->getUri($token_data);
      $opt = $this->getOptions($token_data);
      $exp = 'req.url ~ ' . $invalidation->getExpression();
      // Adds this at the end if this instance has a site name in the
      // configuration, for multi-site pages.
      // the ampersands are url encoded to be %26%26 in sendReq.
      if ($this->getSiteName()) {
        $exp .= "&& req.http.host == " . $this->getSiteName();
      }
      $this->logger->debug("[Regex] ban expression `" . $invalidation->getExpression() . "` was replaced to be: `req.url ~ " . $exp . " `");
      $this->sendReq($invalidation, $uri, $opt, $exp);
    }
  }

}
