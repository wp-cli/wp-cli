# Colorization Fix for Wrapped Table Lines

## Overview

This document provides a complete solution for the issue where colorized text in WP-CLI tables loses color codes when wrapped across multiple lines.

## Issue Description

When using `WP_CLI::colorize()` to add color codes to table values, the ANSI color escape sequences are not preserved when text wraps to fit column widths. This results in:
- First line correctly showing colors
- Continuation lines displaying as plain text (no color)

## Solution Location

The fix must be applied to the `wp-cli/php-cli-tools` library, specifically the `lib/cli/table/Ascii.php` file. This is a dependency of wp-cli/wp-cli.

## Implementation

### Changes Required

The fix involves two main changes to `lib/cli/table/Ascii.php`:

1. **Modify the `row()` method** to track and preserve ANSI color codes across line wraps
2. **Add a `getLastActiveAnsiColor()` helper method** to detect active color codes

### Key Logic

When wrapping colorized text:
1. Detect if a column is pre-colorized
2. Track the active ANSI color code as text is wrapped
3. Before breaking a line:
   - Append `\033[0m` (reset) to the current segment
   - Prepend the active color code to the next segment
4. Adjust string length calculations to account for color codes

### Code Changes

See `patches/php-cli-tools-colorization-fix.patch` for the complete unified diff.

Key modifications in the wrapping loop:
```php
$active_color = '';
do {
    // Prepend active color from previous wrap
    $line_to_wrap = $active_color . $line;
    
    $wrapped_value = \cli\safe_substr( $line_to_wrap, 0, $col_width, true, $encoding );
    
    if ( $val_width ) {
        // Calculate wrapped length (excluding prepended color)
        $wrapped_len = \cli\safe_strlen( $wrapped_value, $encoding );
        if ( $active_color ) {
            $wrapped_len -= \cli\safe_strlen( $active_color, $encoding );
        }
        
        // Get last active color in wrapped segment
        $last_color = $this->getLastActiveAnsiColor( $wrapped_value );
        
        // Calculate remaining text
        $line = \cli\safe_substr( $line, $wrapped_len, null, false, $encoding );
        
        // If more text remains and color is active, add reset and save color
        if ( $last_color && $line ) {
            $wrapped_value .= "\033[0m";
            $active_color = $last_color;
        } else {
            $active_color = '';
        }
        
        $wrapped_lines[] = $wrapped_value;
    }
} while ( $line );
```

## Testing

### Manual Verification

Test scripts have been created to verify the fix:

```bash
# Test basic wrapping
php /tmp/test-colorize-wrap.php | cat -A

# Test long text with multiple wraps
php /tmp/test-long-wrap.php | cat -A

# Comprehensive test suite
php /tmp/test-colorize-comprehensive.php | cat -A
```

Expected output: ANSI codes visible as `^[[31m` (red), `^[[32m` (green), `^[[0m` (reset), etc.

### Behat Test

Added test scenario in `features/formatter.feature` that documents expected behavior.

## Next Steps for Maintainers

1. **Apply to php-cli-tools**:
   - Clone https://github.com/wp-cli/php-cli-tools
   - Apply the patch from `patches/php-cli-tools-colorization-fix.patch`
   - Run php-cli-tools test suite
   - Submit PR to php-cli-tools repository

2. **Update wp-cli dependency**:
   - Once php-cli-tools releases a new version with the fix
   - Update version constraint in wp-cli/wp-cli's composer.json
   - Update composer.lock
   - Test integration

## Files in This PR

- `FIX_DOCUMENTATION.md` - Detailed technical documentation
- `README_COLORIZATION_FIX.md` - This file
- `patches/php-cli-tools-colorization-fix.patch` - Unified diff for the fix
- `features/formatter.feature` - Updated with test scenario
- `/tmp/test-*.php` - Manual test scripts (not committed)

## Benefits

- Colorized table output maintains visual consistency across wrapped lines
- No breaking changes to existing API
- Backwards compatible - only affects pre-colorized columns
- Minimal performance impact

## Related Issues

- Addresses: Wrapped lines in table view break colorization
- Affects: WP-CLI commands that use colorized table output with long text values

## Verification Status

✅ Implementation complete
✅ Manual testing passed
✅ Code style checks passed
✅ No syntax errors
⏳ Awaiting integration into php-cli-tools
⏳ Awaiting dependency update in wp-cli/wp-cli
