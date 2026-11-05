<?php

declare(strict_types=1);

namespace Drupal\Tests\solo\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Form\FormState;
use Drupal\solo\SoloIncludes;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Solo must work on Drupal >= 11.3 without solo.theme.
 *
 * WHY THIS EXISTS
 * Drupal 11.5.0 deprecated <theme>.theme files ("Using solo.theme is
 * deprecated ... removed from drupal:13.0.0"). Core skips that notice when a
 * function in the file carries #[ExtensionFileIsConverted], and that
 * attribute's own documentation sets the condition: "the extension must fully
 * work without the file in versions >=11.3.0". Solo used to break that
 * condition: solo.theme require_once'd the include files, and 44 helper
 * functions the src/Hook classes call were defined only there, so without the
 * file every page would fatal on an undefined function.
 *
 * Now every #[Hook] method calls \Drupal\solo\SoloIncludes::load() first, and
 * SoloIncludes::FILES is the one list of the include files. Nothing goes red
 * on Drupal 12 if this breaks, because Drupal 12 still loads solo.theme, so
 * this test is what keeps the marker a true statement before Drupal 13 stops
 * loading the file.
 *
 * WHAT THIS ENFORCES
 * 1. solo.theme carries #[ExtensionFileIsConverted]. What it loads is
 *    ThemeFileIncludesTest's job: it must name no Solo class (#3628961).
 * 2. Every #[Hook] method in src/Hook starts with SoloIncludes::load().
 * 3. Every include-defined function that src/ calls or names as a string
 *    callback is defined in a file listed in SoloIncludes::FILES.
 * 4. Every SoloIncludes::FILES entry exists.
 * 5. Every #[Hook] method that names an include-defined function as a string
 *    callback (#validate, #submit) also calls
 *    SoloIncludes::addToFormBuildInfo(), so a form rebuilt from the form cache
 *    loads the includes without solo.theme.
 * 6. SoloIncludes::addToFormBuildInfo() lists every include as a path relative
 *    to the Drupal root, keeps existing entries and adds none twice.
 *
 * Requirements 1-5 read the files as tokens rather than booting Drupal, so
 * they run in milliseconds and on every core version alike.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @group solo
 */
#[Group('solo')]
class ThemeFileIndependenceTest extends TestCase {

  /**
   * The theme root.
   */
  private function themePath(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Returns a file's tokens with whitespace and comments removed.
   *
   * @return list<array{0: int|null, 1: string, 2: int}>
   *   Each token as [token id or NULL, text, line].
   */
  private function tokens(string $file): array {
    $result = [];
    $line = 1;
    foreach (token_get_all((string) file_get_contents($file)) as $token) {
      if (is_array($token)) {
        $line = $token[2];
        if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], TRUE)) {
          continue;
        }
        $result[] = [$token[0], $token[1], $token[2]];
      }
      else {
        $result[] = [NULL, $token, $line];
      }
    }
    return $result;
  }

  /**
   * Maps every top-level function defined in includes/ to its file.
   *
   * @return array<string, string>
   *   The include file's basename, keyed by function name.
   */
  private function includeFunctions(): array {
    $functions = [];
    foreach (glob($this->themePath() . '/includes/*.inc') ?: [] as $file) {
      $tokens = $this->tokens($file);
      foreach ($tokens as $i => $token) {
        if ($token[0] === T_FUNCTION && isset($tokens[$i + 1]) && $tokens[$i + 1][0] === T_STRING) {
          $functions[$tokens[$i + 1][1]] = basename($file);
        }
      }
    }
    return $functions;
  }

  /**
   * Lists the PHP files under src/.
   *
   * @return list<string>
   *   Absolute paths.
   */
  private function srcFiles(): array {
    $files = [];
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->themePath() . '/src', \FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
      if ($file->getExtension() === 'php') {
        $files[] = $file->getPathname();
      }
    }
    sort($files);
    return $files;
  }

  /**
   * Requirement 1: solo.theme declares the #[ExtensionFileIsConverted] marker.
   */
  public function testThemeFileDeclaresExtensionFileIsConverted(): void {
    $file = $this->themePath() . '/solo.theme';
    $text = implode('', array_column($this->tokens($file), 1));

    // What solo.theme loads, and that it names no Solo class, is enforced by
    // ThemeFileIncludesTest (#3628961).
    $this->assertStringContainsString('#[ExtensionFileIsConverted]', $text, 'solo.theme must carry #[ExtensionFileIsConverted], or core reports it as deprecated.');
  }

  /**
   * Requirement 2: every #[Hook] method loads the includes first.
   */
  public function testEveryHookMethodLoadsTheIncludes(): void {
    $checked = 0;
    $missing = [];
    foreach (glob($this->themePath() . '/src/Hook/*.php') ?: [] as $file) {
      $tokens = $this->tokens($file);
      $count = count($tokens);
      for ($i = 0; $i < $count; $i++) {
        if ($tokens[$i][0] !== T_ATTRIBUTE || !isset($tokens[$i + 1]) || $tokens[$i + 1][1] !== 'Hook') {
          continue;
        }
        // The method this attribute belongs to, and its opening brace.
        for ($f = $i; $f < $count && $tokens[$f][0] !== T_FUNCTION; $f++);
        for ($b = $f; $b < $count && $tokens[$b][1] !== '{'; $b++);
        $method = $tokens[$f + 1][1] ?? '?';
        $first = implode('', array_column(array_slice($tokens, $b + 1, 6), 1));
        $checked++;
        if ($first !== 'SoloIncludes::load();') {
          $missing[] = sprintf('%s::%s (line %d) starts with "%s"', basename($file, '.php'), $method, $tokens[$f][2], $first);
        }
        $i = $b;
      }
    }

    // A scan that finds nothing proves nothing: Solo had 41 #[Hook] methods
    // when this was written.
    $this->assertGreaterThanOrEqual(38, $checked, sprintf('Scanned %d #[Hook] methods; expected at least 38.', $checked));
    $this->assertSame([], $missing, "#[Hook] methods that do not call SoloIncludes::load() first -- they fatal without solo.theme:\n" . implode("\n", $missing));
  }

  /**
   * Requirement 3: every include function src/ uses is in a listed file.
   */
  public function testEveryIncludeFunctionUsedBySrcIsListed(): void {
    $defined_in = $this->includeFunctions();
    $listed = array_flip(SoloIncludes::FILES);
    $used = [];
    $unlisted = [];
    $not_a_call = [
      T_OBJECT_OPERATOR,
      T_NULLSAFE_OBJECT_OPERATOR,
      T_DOUBLE_COLON,
      T_FUNCTION,
      T_NEW,
    ];
    foreach ($this->srcFiles() as $file) {
      $tokens = $this->tokens($file);
      foreach ($tokens as $i => $token) {
        $name = NULL;
        // A plain function call: a name followed by "(" that is not a
        // method, a static call, a declaration or a constructor.
        if ($token[0] === T_STRING && ($tokens[$i + 1][1] ?? '') === '(' && !in_array($tokens[$i - 1][0] ?? NULL, $not_a_call, TRUE)) {
          $name = $token[1];
        }
        // A callback named by string, such as '#validate' or '#submit'.
        elseif ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
          $name = trim($token[1], '\'"');
        }
        if ($name === NULL || !isset($defined_in[$name])) {
          continue;
        }
        $used[$name] = TRUE;
        if (!isset($listed[$defined_in[$name]])) {
          $unlisted[] = sprintf('%s() used at %s:%d is defined in includes/%s, which SoloIncludes::FILES does not load', $name, basename($file), $token[2], $defined_in[$name]);
        }
      }
    }

    // Measured 2026-10-11: src/ uses 44 helper functions from includes/ (plus
    // the string callbacks). A broken scan must go red, not pass on nothing.
    $this->assertGreaterThanOrEqual(40, count($used), sprintf('Found %d include functions used by src/; expected at least 40.', count($used)));
    $this->assertSame([], $unlisted, "Include functions src/ needs that nothing loads without solo.theme:\n" . implode("\n", array_unique($unlisted)));
  }

  /**
   * Requirement 4: every listed include file exists.
   */
  public function testEveryListedIncludeExists(): void {
    foreach (SoloIncludes::FILES as $file) {
      $this->assertFileExists($this->themePath() . '/includes/' . $file, sprintf('SoloIncludes::FILES lists %s, which does not exist.', $file));
    }
  }

  /**
   * Requirement 5: a hook naming an include callback registers the includes.
   */
  public function testHooksNamingIncludeCallbacksRegisterTheIncludes(): void {
    $defined_in = $this->includeFunctions();
    $naming = 0;
    $missing = [];
    foreach (glob($this->themePath() . '/src/Hook/*.php') ?: [] as $file) {
      $tokens = $this->tokens($file);
      $count = count($tokens);
      for ($i = 0; $i < $count; $i++) {
        if ($tokens[$i][0] !== T_ATTRIBUTE || !isset($tokens[$i + 1]) || $tokens[$i + 1][1] !== 'Hook') {
          continue;
        }
        // The method body: from its opening brace to the matching close.
        for ($f = $i; $f < $count && $tokens[$f][0] !== T_FUNCTION; $f++);
        for ($b = $f; $b < $count && $tokens[$b][1] !== '{'; $b++);
        $depth = 0;
        for ($e = $b; $e < $count; $e++) {
          // "{", and the "{" / "${" that open interpolation in a string.
          if (in_array($tokens[$e][1], ['{', '${'], TRUE)) {
            $depth++;
          }
          elseif ($tokens[$e][1] === '}' && --$depth === 0) {
            break;
          }
        }
        $body = array_slice($tokens, $b, $e - $b + 1);
        $callbacks = [];
        foreach ($body as $token) {
          $name = $token[0] === T_CONSTANT_ENCAPSED_STRING ? trim($token[1], '\'"') : NULL;
          if ($name !== NULL && isset($defined_in[$name])) {
            $callbacks[] = $name;
          }
        }
        $i = $e;
        if ($callbacks === []) {
          continue;
        }
        $naming++;
        if (!str_contains(implode('', array_column($body, 1)), 'SoloIncludes::addToFormBuildInfo($form_state);')) {
          $missing[] = sprintf('%s::%s (line %d) names %s', basename($file, '.php'), $tokens[$f + 1][1] ?? '?', $tokens[$f][2], implode(', ', $callbacks));
        }
      }
    }

    // Measured 2026-10-11: two methods name include callbacks
    // (formSystemThemeSettingsAlter, formSearchFormAlter). A scan that finds
    // none proves nothing.
    $this->assertGreaterThanOrEqual(2, $naming, sprintf('Found %d #[Hook] methods naming include callbacks; expected at least 2.', $naming));
    $this->assertSame([], $missing, "#[Hook] methods that name an include callback but do not call SoloIncludes::addToFormBuildInfo(\$form_state) -- a cached rebuild without solo.theme would call an undefined function:\n" . implode("\n", $missing));
  }

  /**
   * Requirement 6: the build info lists every include once, root-relative.
   */
  public function testAddToFormBuildInfoListsEveryIncludeOnce(): void {
    $resolver = $this->createMock(ExtensionPathResolver::class);
    $resolver->method('getPath')->with('theme', 'solo')->willReturn('themes/contrib/solo');
    $container = new ContainerBuilder();
    $container->set('extension.path.resolver', $resolver);
    \Drupal::setContainer($container);

    try {
      $form_state = new FormState();
      $form_state->addBuildInfo('files', [
        'core/modules/system/system.admin.inc',
        'themes/contrib/solo/includes/_solo_theme_alter.inc',
      ]);
      // Twice, as the settings alter runs more than once per build.
      SoloIncludes::addToFormBuildInfo($form_state);
      SoloIncludes::addToFormBuildInfo($form_state);
      $files = $form_state->getBuildInfo()['files'];
    }
    finally {
      \Drupal::unsetContainer();
    }

    $expected = ['core/modules/system/system.admin.inc'];
    foreach (SoloIncludes::FILES as $file) {
      $expected[] = 'themes/contrib/solo/includes/' . $file;
    }
    sort($expected);
    $actual = $files;
    sort($actual);
    $this->assertSame($expected, $actual, 'The form build info must keep existing files and list every SoloIncludes::FILES entry exactly once, relative to the Drupal root.');
  }

}
