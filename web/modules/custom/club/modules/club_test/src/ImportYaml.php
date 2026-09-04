<?php

namespace Drupal\club;

use Symfony\Component\Yaml\Yaml;

/**
 * Override default config set by core/contrib modules; setData() replaces all settings.
 *
 * Method is called by .install in: club_civicrm, club_event, club_media, club_user.
 *
 */
class ImportYaml {

  public static function readNewYaml($module, $files) {

    foreach($files as $file) {
      $config_file = \Drupal::service('extension.list.module')
        ->getPath($module) . '/config/static/' . $file . '.yml';

      if (is_file($config_file)) {
        $settings = Yaml::parse((string) file_get_contents($config_file));

        if (is_array($settings)) {
          $config = \Drupal::configFactory()->getEditable($file);
          $config->setData($settings)->save(TRUE);
        }
      }
    }
  }
}
