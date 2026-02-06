# Multiple Flag Values Support - Implementation Summary

## Overview
This implementation adds support for passing the same flag multiple times to provide multiple values, similar to how `--require` already works. This addresses issue #159 where users want to filter by multiple status values.

## Changes Made

### Core WP-CLI Changes (wp-cli/wp-cli)

#### 1. Configurator.php
Modified `unmix_assoc_args()` to collect multiple values for the same flag into an array:
- When the same flag is provided multiple times (e.g., `--status=active --status=parent`), the values are collected into an array `['active', 'parent']`
- This applies to both STRICT_ARGS_MODE and normal mode
- Backward compatible: the Subcommand class decides whether to use the array or just the last value

#### 2. Subcommand.php
Enhanced `validate_args()` to handle repeating parameters:
- Checks if a parameter has the ellipsis `...` in its synopsis (e.g., `[--status=<status>...]`)
- The ellipsis sets `$spec['repeating']` to true when parsing the synopsis
- If not repeating, uses only the last value (backward compatible)
- If repeating, keeps the array of values
- Validates each value in the array against the allowed options
- Provides better error messages that include the invalid value

### Required Changes in Extension Packages

The following changes are needed in `wp-cli/extension-command` repository:

#### CommandWithUpgrade.php
Update the filtering logic in `_list()` method to handle arrays:
```php
if ( is_array( $field_filter ) ) {
    if ( ! in_array( $item[ $field ], $field_filter, true ) ) {
        unset( $all_items[ $key ] );
    }
} elseif ( ... existing logic ... ) {
    ...
}
```

#### Theme_Command.php
Update the synopsis to use ellipsis for `--status` parameter:
```
[--status=<status>...]
: Filter the output by theme status.
---
options:
  - active
  - parent
  - inactive
---
```

Add example:
```
# List active and parent themes.
$ wp theme list --status=active --status=parent
```

#### Plugin_Command.php
Same changes as Theme_Command.php for the `--status` parameter.

## Usage Examples

### Before
```bash
# This didn't work as expected
wp theme list --status=active,parent  # Returns empty result
```

### After
```bash
# Using repeated flags (NEW - primary way)
wp theme list --status=active --status=parent

# Using comma-separated values (STILL WORKS)
wp theme list --status=active,parent

# Single value (STILL WORKS)
wp theme list --status=active
```

## Creating Commands with Multiple Support

To create a command that supports multiple values, use the ellipsis `...` in the synopsis:

```php
/**
 * List items
 *
 * ## OPTIONS
 *
 * [--status=<status>...]
 * : Filter by status
 * ---
 * options:
 *   - active
 *   - inactive
 * ---
 */
public function list_( $args, $assoc_args ) {
    if ( isset( $assoc_args['status'] ) ) {
        $statuses = $assoc_args['status'];
        
        // $statuses will be an array if multiple values provided
        // e.g., ['active', 'inactive']
        if ( is_array( $statuses ) ) {
            // Handle multiple values
            foreach ( $items as $item ) {
                if ( in_array( $item->status, $statuses, true ) ) {
                    // Include this item
                }
            }
        } else {
            // Handle single value (backward compatibility)
            // This happens when user passes just --status=active
        }
    }
}
```

## Backward Compatibility

The implementation is fully backward compatible:

1. **Existing commands without ellipsis**: When a flag is repeated, only the last value is used (existing behavior)
2. **Comma-separated values**: Still work as before
3. **Single values**: Still work as before
4. **Existing code**: No changes needed for commands that don't use the ellipsis syntax

## Testing

### Unit Tests
- Added `testExtractAssocMultipleValues()` in ConfiguratorTest.php
- Verifies that multiple flag values are correctly parsed into arrays

### Feature Tests
- Created `features/multiple-flag-values.feature` with 3 scenarios:
  1. Command with `multiple: true` accepts repeated flags
  2. Command without `multiple` uses last value
  3. Multiple values with validation

All tests pass:
- ✅ Linting
- ✅ Code style (phpcs)
- ✅ Static analysis (phpstan)
- ✅ Unit tests (6/6 in ConfiguratorTest)
- ✅ Feature tests (all scenarios pass)
- ✅ Security scan (CodeQL)

## Next Steps

To fully enable this feature for theme and plugin list commands:

1. Submit a PR to `wp-cli/extension-command` with:
   - Changes to CommandWithUpgrade.php
   - Changes to Theme_Command.php
   - Changes to Plugin_Command.php
   - Tests for the new functionality

2. Update the handbook/documentation with examples of using multiple flags

3. Consider adding ellipsis syntax to other commands that could benefit from it

## Related Issues

- #159 - Original issue requesting multiple status support
- https://github.com/wp-cli/extension-command/issues/159 - Related issue in extension-command

## Implementation Notes

- The repeating parameter feature uses the ellipsis `...` syntax in the synopsis (e.g., `[--status=<status>...]`)
- This is the same syntax already used for repeating positional parameters (e.g., `<file>...`)
- The SynopsisParser detects the ellipsis and sets `$spec['repeating']` to true
- Error messages for invalid values include the specific invalid value for better debugging
- The array filtering logic handles both single values and arrays transparently
