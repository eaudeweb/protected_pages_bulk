# Protected Pages Bulk Setup

## Overview

The **Protected Pages Bulk Setup** module extends the functionality of the [Protected Pages](https://www.drupal.org/project/protected_pages) module by allowing content managers to configure protection settings for multiple pages at once.

Instead of setting up protection page-by-page, this module introduces a bulk interface where you can:

- Apply the same **expiration date** to multiple pages.
- Set a **common password** for several pages simultaneously.

This helps streamline workflows for managing private content, event-based publications, or limited-time access pages.

## Features

- Bulk selection of nodes for protection.
- Set password and expiration for multiple pages at once.
- Works alongside the Protected Pages module.

## Requirements

- Drupal 10+
- [Protected Pages](https://www.drupal.org/project/protected_pages)

## Installation

1. Install the module via Composer or manually:
   ```bash
   composer require drupal/protected_pages_bulk