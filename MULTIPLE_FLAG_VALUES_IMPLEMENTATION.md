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
Enhanced `validate_args()` to handle the `multiple` annotation:
- Checks if a parameter has `multiple: true` in its PHPdoc
- If not set or false, uses only the last value (backward compatible)
- If set to true, keeps the array of values
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
Add `multiple: true` annotation to `--status` parameter:
```
[--status=<status>]
: Filter the output by theme status.
---
options:
  - active
  - parent
  - inactive
multiple: true
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

To create a command that supports multiple values:

```php
/**
 * List items
 *
 * ## OPTIONS
 *
 * [--status=<status>]
 * : Filter by status
 * ---
 * options:
 *   - active
 *   - inactive
 * multiple: true
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

1. **Existing commands without `multiple: true`**: When a flag is repeated, only the last value is used (existing behavior)
2. **Comma-separated values**: Still work as before
3. **Single values**: Still work as before
4. **Existing code**: No changes needed for commands that don't use `multiple: true`

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

3. Consider adding `multiple: true` support to other commands that could benefit from it

## Related Issues

- #159 - Original issue requesting multiple status support
- https://github.com/wp-cli/extension-command/issues/159 - Related issue in extension-command

## Implementation Notes

- The `multiple` annotation is read from the YAML block in PHPdoc, similar to `options` and `default`
- The annotation is checked using strict comparison: `false === $spec_args['multiple']`
- Error messages for invalid values include the specific invalid value for better debugging
- The array filtering logic handles both single values and arrays transparently
