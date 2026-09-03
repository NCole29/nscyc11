<?php

namespace Drupal\club;

use Drupal\shortcut\Entity\Shortcut;

class ShortcutMenu {

  public static function addShortcuts($shortcuts) {

    foreach($shortcuts as $shortcut) {
        $short_cut = Shortcut::create([
          'shortcut_set' => $shortcut[0],
          'weight' => $shortcut[1],
          'title' => $shortcut[2],
          'link' => [
            'uri' => $shortcut[3],
          ],
        ]);
        $short_cut->save();
      }
    }
  }
