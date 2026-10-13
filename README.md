# Local Dictionaries

Local plugin to manage reference dictionaries for repeatable profile fields.

## Requirements

- Moodle 4.5+ (tested through Moodle 5.2)
- Database baseline from Moodle: PostgreSQL 13+ or MySQL 8.0+
- Works with Moodle DB abstraction (no vendor-specific SQL)
- Intended integration with `profilefield_repeatable` plugin

## Features

- Create and update reference domains
- Import code and label pairs from CSV
- Resolve labels by code via web services
- Cache-backed bulk label resolution
- Cross-database compatible schema (`local_dictionaries_domain`, `local_dictionaries_item`)

## Installation

1. Copy this plugin to `local/dictionaries`.
2. Visit the Site Administration notifications page to complete the installation.

## Usage

- Go to Site Administration -> Plugins -> Local plugins -> Manage repeatable reference dictionaries.
- Create a domain and import CSV content or upload a CSV file.

## Web Services

This plugin provides web service functions for upserting reference items and resolving labels.
You must grant the capability `local/dictionaries:manage` to allow access.

## Notes

- This plugin stores reference dictionaries only (no personal profile data).
- Keep this plugin installed when using domain mappings in `profilefield_repeatable`.
