<?php

namespace Drupal\section_purger\Plugin\monitoring\SensorPlugin;

use Drupal\key\KeyRepositoryInterface;
use Drupal\monitoring\Entity\SensorConfig;
use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\SensorPlugin\SensorPluginBase;
use Drupal\section_purger\Entity\SectionPurgerSettings;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\monitoring\SensorPlugin\SensorPluginInterface;


/**
 * Monitors the Section API connection.
 *
 * @SensorPlugin(
 *   id = "section_purger",
 *   label = @Translation("Section Purger Sensor"),
 *   description = @Translation("Monitors connectivity to the Section API"),
 * )
 */

class SectionPurgerSensorPlugin extends SensorPluginBase implements SensorPluginInterface {

  /**
   * The client interface.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * The key repository.
   *
   * @var \Drupal\key\KeyRepository
   */
  protected $keyRepository;

  /**
   * {@inheritdoc}
   */
  public function __construct(SensorConfig $sensor_config, $plugin_id, $plugin_definition, ClientInterface $http_client, KeyRepositoryInterface $key_repository) {
    parent::__construct($sensor_config, $plugin_id, $plugin_definition);
    $this->client = $http_client;
    $this->keyRepository = $key_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, SensorConfig $sensor_config, $plugin_id, $plugin_definition) {
    return new static(
      $sensor_config,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('key.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultConfiguration() {
    return [
      'caching_time' => 60 * 5,
      'value_type' => 'bool',
      'category' => 'Tide',
      'settings' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [
      'module' => ['section_purger'],
    ];
  }


  /**
   * Get the Section URI.
   *
   * @return string
   *   The section URI.
   */
  protected function getUri(SectionPurgerSettings $settings) {
    return sprintf(
      '%s://%s:%s/api/v1/account/%s/application/%s/environment/%s',
      $settings->scheme,
      $settings->hostname,
      $settings->port,
      $settings->account,
      $settings->application,
      $settings->environmentname
    );
  }

  /**
   * Get request options.
   *
   * @param \Drupal\section_purger\Entity\SectionPurgerSettings $settings
   *   The purger settings.
   *
   * @param
   */
  protected function getOptions(SectionPurgerSettings $settings) {
    $opt = [
      'auth' => [$settings->username, $this->keyRepository->getKey($settings->password)->getKeyValue()],
      'connect_timeout' => $settings->connect_timeout,
      'timeout' => $settings->timeout,
    ];

    return $opt;
  }

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $sensor_result) {
    $purgers = SectionPurgerSettings::loadMultiple();
    $purger = reset($purgers);

    if (empty($purger)) {
      $sensor_result->setStatus(SensorResultInterface::STATUS_WARNING);
      $sensor_result->setMessage('Section purger is not configured.');
      return;
    }

    $uri = $this->getUri($purger);
    $opt = $this->getOptions($purger);

    // Sensor interface catches raised exceptions - Guzzle will throw
    // an exception when HTTP >= 400.
    $this->client->get($uri, $opt);

    $sensor_result->setStatus(SensorResultInterface::STATUS_OK);
    $sensor_result->setMessage('Successfully connected to Section.io.');
  }

}