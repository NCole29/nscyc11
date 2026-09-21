# FAQ Field

This module provides a field for frequently asked questions.
Added, you can create simple but smooth FAQs on any piece of content.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/faqfield).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/faqfield).


## Table of contents

- Requirements
- Installation
- Configuration
- Usage
- Maintainers


## Requirements

This module requires no modules outside of Drupal core.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

The module has no menu or modifiable settings. There is no configuration. When
enabled, the module will prevent the links from appearing. To get the links
back, disable the module and clear caches.


## Usage

You can add the field to any entity (eg. content type, users, ..) as usual.
On the fields settings you can choose what kind of answer input widget should
be used and how it should be filtered. If you choose a formattable textarea
you can configure the default filter by the default value preview on the field
settings page and there are several advanced options you can make use of.

You have the choice of two display formatters:
- Accordion (jQuery JavaScript animated show / hide) [Default]
- Simple text (none formatted, simple output for custom theming)
- Definition list (typical <dl> FAQ list)
- Anchor list (a FAQ with anchor link list)
If you are using accordion you can modify its behavior easily by the display
settings.


## Maintainers

- Oleksandr Dekhteruk - [pifagor](https://www.drupal.org/u/pifagor)
- Patrick Drotleff - [patrickd](https://www.drupal.org/u/patrickd)
- Lucas Hedding - [pifagor](https://www.drupal.org/u/heddn)
