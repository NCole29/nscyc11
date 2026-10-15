Solo Sub-theme is a start up theme for your customization.

## Creating your own sub-theme from this folder

Copy this folder out of the Solo theme (for example to
`themes/custom/my_theme`), then replace **every** `solo_subtheme` with your
theme's machine name. Your machine name uses lowercase letters, numbers and
underscores, and starts with a letter. The examples below use `my_theme`.

1. **Rename the files:**
   - `solo_subtheme.info.yml` → `my_theme.info.yml`
   - `solo_subtheme.libraries.yml` → `my_theme.libraries.yml`
   - `solo_subtheme.theme` → `my_theme.theme`
   - `config/install/solo_subtheme.settings.yml` → `config/install/my_theme.settings.yml`
   - `config/schema/solo_subtheme.schema.yml` → `config/schema/my_theme.schema.yml`
2. **Replace `solo_subtheme` inside every file**, including the PHP files:
   - `src/Hook/SoloSubthemeHooks.php`: change
     `namespace Drupal\solo_subtheme\Hook;` to `namespace Drupal\my_theme\Hook;`,
     and `'solo_subtheme_'` to `'my_theme_'`.
   - `my_theme.theme`: change
     `use Drupal\solo_subtheme\Hook\SoloSubthemeHooks;` to
     `use Drupal\my_theme\Hook\SoloSubthemeHooks;`, and the function name
     `solo_subtheme_theme_suggestions_block_alter` to
     `my_theme_theme_suggestions_block_alter`.
   - `my_theme.info.yml`: the library line `solo_subtheme/solo-subtheme-global`
     becomes `my_theme/solo-subtheme-global`.
   - `config/schema/my_theme.schema.yml`: `solo_subtheme.settings:` becomes
     `my_theme.settings:`.
3. **Check that nothing was missed.** From your theme folder this must print
   nothing:

   ```
   grep -rn "solo_subtheme" .
   ```

4. Clear the cache (`drush cr`) and enable your theme.

**Skip the namespace and you get a fatal error.** Drupal looks for the class
under your theme's name (`Drupal\my_theme\Hook\…`), so a file that still says
`namespace Drupal\solo_subtheme\Hook;` fails with "Cannot redeclare class
Drupal\solo_subtheme\Hook\SoloSubthemeHooks". The same applies when you copy
new files from a later Solo release into an existing sub-theme: rename them
the same way.
