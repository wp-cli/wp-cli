# Step-Based Progress Display Feature

## Overview

This feature adds support for customizable format strings to the `Bar` progress indicator, allowing developers to display step-based progress (e.g., "5/10") instead of or in addition to percentage-based progress (e.g., "50%").

## Important Note

This PR includes modifications to `vendor/wp-cli/php-cli-tools/lib/cli/progress/Bar.php`. Normally vendor files should not be tracked in version control. These changes are included here for demonstration purposes. The proper workflow would be to:

1. Submit these changes to the upstream `wp-cli/php-cli-tools` repository
2. Once accepted, update the dependency version in `composer.json`
3. Run `composer update` to pull in the new version

## Changes Made

### Modified Files

- `vendor/wp-cli/php-cli-tools/lib/cli/progress/Bar.php`
  - Added constructor to accept optional `$formatMessage` parameter
  - Modified `display()` method to pass `current` and `total` values to `Streams::render()`

## New Features

### Constructor Parameter

The `Bar` class now accepts an optional fourth parameter for custom format strings:

```php
public function __construct($msg, $total, $interval = 100, $formatMessage = null)
```

### Available Placeholders

The format string now supports the following placeholders:

- `{:msg}` - The progress message
- `{:percent}` - The percentage complete (0-100)
- `{:current}` - The current step count
- `{:total}` - The total number of steps

## Usage Examples

### Example 1: Default Behavior (Backward Compatible)

```php
$progress = new \cli\progress\Bar('Processing items', 100);
// Output: "Processing items  50% [=========>         ]  0:05 / 0:10"
```

### Example 2: Step-Based Format

```php
$progress = new \cli\progress\Bar('Downloading files', 100, 100, '{:msg}  {:current}/{:total} [');
// Output: "Downloading files  50/100 [=========>         ]  0:05 / 0:10"
```

### Example 3: Mixed Format

```php
$progress = new \cli\progress\Bar('Syncing data', 100, 100, '{:msg}  {:percent}% ({:current}/{:total}) [');
// Output: "Syncing data  50% (50/100) [=========>         ]  0:05 / 0:10"
```

### Example 4: Custom Separators

```php
$progress = new \cli\progress\Bar('Building', 100, 100, '{:msg}: {:current} of {:total} [');
// Output: "Building: 50 of 100 [=========>         ]  0:05 / 0:10"
```

## Backward Compatibility

The changes are fully backward compatible. Existing code will continue to work without modification, using the default percentage-based format.

## Testing

Run the example file to see all formats in action:

```bash
php examples/step-based-progress.php
```

## Benefits

1. **Flexibility**: Developers can now choose how to display progress based on their needs
2. **Clarity**: Step-based formats can be more intuitive for certain use cases (e.g., "Downloaded 5/10 files")
3. **Customization**: Full control over the format string allows for any combination of available placeholders
4. **Backward Compatible**: No breaking changes to existing code
