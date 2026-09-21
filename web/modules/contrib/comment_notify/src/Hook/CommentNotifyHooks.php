<?php

namespace Drupal\comment_notify\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Hook implementations for Comment Notify.
 */
class CommentNotifyHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.comment_notify':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('Comment Notify is a lightweight tool to send notification e-mails
        to visitors about new,
        published comments on pages where they have commented.
        Comment Notify works for both registered and anonymous users.
        Providing comment notifications for anonymous users is an important tool
        in bringing anonymous users back to your site, which helps convert anonymous
        users to registered users. Anonymous comment notification is a critical tool in
        building a blog comment community; all the major blogging platforms include this
        functionality.') . '</p>';
        $output .= '<h3>' . $this->t('Uses') . '</h3>';
        $output .= '<p>' . $this->t('Can be used to send notification e-mails to visitors about new, published comments on pages where they have commented. For more information about the module, please refer to the module <a href="https://www.drupal.org/node/252697">Documentation Page</a>') . '</p>';
        $output .= '<h3>' . $this->t('Configuring the module') . '</h3>';
        $extend_url = Url::fromRoute('system.modules_list')->toString();
        $output .= '<ol>
        <li> Enable the module from the <a href="' . $extend_url . '">Extend</a> page </li>
        <br>
        <li> Grant permission to use this module from
        - <a href="/admin/people/permissions#module-comment_notify">Permissions</a>
        page.</li> <br>
        <li> Set permissions for commenting as per usual from
        - <a href="/admin/people/permissions#module-comment">Permission per user</a>
        page.</li> <br>
        <li> Configure the settings for comments field for content types from the your manage fields section of the content type.<br>
        Edit your comment field:<br>
        Look for "Anonymous commenting" and set to either:
          "Anonymous posters may leave their contact information" OR
          "Anonymous posters must leave their contact information" </li> <br>
        <li> Configure this module at
        - <a href="/admin/config/people/comment_notify">Configuration page.</a><br>
        -Determine which content types to activate it for.<br>
        -Determine which subscription modes are allowed.<br>
        -Configure the templates for the e-mails. </li> <br>
        <li> Set your node-notify settings per user (optional) </li>

        </ol>';
        $output .= '<p> The module includes a feature to notify the node author of all comments
        on their nodes. To enable this go to "My account" > Edit (e.g. user/1/edit)
        and change the settings there, i.e., "Comment follow-up notification settings" </p>';

        return $output;
    }

    return NULL;
  }

}
