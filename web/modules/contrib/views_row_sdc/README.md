# Views Row SDC

Provides an easy way to display the output of a view using a single directory
component. Select a component and connect View fields to props and slots. No
need for complicated template files.

## Features

- Adds a new "Show" option under format, alongside "Content view mode" and "Fields" you can select "Single Directory component."
- Provides a list of components available on the site under settings.
- Lists Props and Slots of the component, and allows View fields to be mapped to them.
- Renders the selected SDC for each row of the view result.

## Post-Installation

1. Install the module in the usual way.
2. Create or edit a view.
3. In the view editor, under "Format", change from "Content view mode" or "Fields" to "Single Directory Component".
4. In Settings, select the SDC to use from the drop-down list of components.
5. Also in settings, for each Prop and Slot, select the Views field to map to.

**Note:** it's the responsibility of the View maintainer to ensure that the fields
being mapped to properties contain compatible data.

## Additional Requirements

This module has no additional requirements, but you do need to have some Single
Directory Components available on your site. Please see the
[Drupal Using Single Directory Components](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components)
documentation for more details on building and using components.

## Recommended modules/libraries

None (perhaps some modules providing SDC components could be suggested here).

## Similar projects

- UI patterns "UI patterns views" module provides similar functionality, however, it is a lot of overhead to install the entire UI Patterns module just to use it. Definitely a more complete solution, but best used alongside the rest of the UI patterns family.
- Single Directory Components: Display allows a piece of content to be displayed using a SDC. This can then be displayed in a view using the "Content view mode" option. Works well as long as long as all the values you wish to display are within the same entity.
- Component views seems to be more about creating views to browse the component library. I haven't installed it yet, but will add more when I do.

## Supporting this Module

This module is aimed at making it easier to incorporate Views into low code/no
code solutions like Drupal CMS. I think there is scope for incorporating into
solutions like Drupal Canvas. If you think you could help with this, please get
in touch, or dive into the issue queue!
