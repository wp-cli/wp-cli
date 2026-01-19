# Fix for Table Colorization Issue

## Problem
When using `WP_CLI::colorize()` for values displayed in tables, line wrapping breaks the colorization. The wrapped text loses its color codes, resulting in plain text on continuation lines.

## Root Cause
The issue is in the `wp-cli/php-cli-tools` library, specifically in the `cli\table\Ascii` class's `row()` method. When text is wrapped to fit column widths, ANSI color escape sequences are not preserved across line breaks.

## Solution Overview
The fix modifies the text wrapping logic in the Ascii table renderer to:
1. Detect active ANSI color codes when wrapping text
2. Terminate each wrapped segment with a reset code (`\033[0m`)
3. Prepend the active color code to continuation lines
4. Properly calculate string lengths excluding color codes

## Implementation Details

### File: `vendor/wp-cli/php-cli-tools/lib/cli/table/Ascii.php`

#### 1. Modified `row()` method (lines 132-224)

The key changes in the wrapping loop:

```php
foreach ( $split_lines as $line ) {
    $active_color = '';
    do {
        // Add active color from previous wrap to the beginning
        $line_to_wrap = $active_color . $line;
        
        $wrapped_value = \cli\safe_substr( $line_to_wrap, 0, $col_width, true /*is_width*/, $encoding );
        $val_width     = Colors::width( $wrapped_value, self::isPreColorized( $col ), $encoding );
        if ( $val_width ) {
            // Calculate the actual length of wrapped content (excluding prepended color code)
            $wrapped_len = \cli\safe_strlen( $wrapped_value, $encoding );
            if ( $active_color ) {
                $wrapped_len -= \cli\safe_strlen( $active_color, $encoding );
            }
            
            // Get the last active ANSI color code from the wrapped portion
            $last_color = '';
            if ( self::isPreColorized( $col ) ) {
                $last_color = $this->getLastActiveAnsiColor( $wrapped_value );
            }
            
            // Calculate remaining line
            $line = \cli\safe_substr( $line, $wrapped_len, null /*length*/, false /*is_width*/, $encoding );
            
            // If there's an active color and more text to wrap, add reset code
            if ( $last_color && $line ) {
                $wrapped_value .= "\033[0m";
                $active_color = $last_color;
            } else {
                $active_color = '';
            }
            
            $wrapped_lines[] = $wrapped_value;
        }
    } while ( $line );
}
```

#### 2. Added `getLastActiveAnsiColor()` helper method (lines 266-290)

```php
/**
 * Get the last active ANSI color code from a string.
 * 
 * Finds the last ANSI color escape sequence that hasn't been reset.
 * ANSI color codes follow the pattern: \033[<number>m or \033[<number>;<number>m
 * Reset is: \033[0m
 * 
 * @param string $string The string to analyze.
 * @return string The last active ANSI color code, or empty string if none.
 */
private function getLastActiveAnsiColor( $string ) {
    // Pattern to match ANSI escape sequences
    preg_match_all( '/\033\[([0-9;]+)m/', $string, $matches, PREG_OFFSET_CAPTURE );
    
    if ( empty( $matches[0] ) ) {
        return '';
    }
    
    // Walk through matches from the end to find the last active color
    for ( $i = count( $matches[0] ) - 1; $i >= 0; $i-- ) {
        $code = $matches[1][ $i ][0];
        $full_match = $matches[0][ $i ][0];
        
        // If we find a reset code (0), there's no active color
        if ( $code === '0' ) {
            return '';
        }
        
        // Otherwise, this is the last active color
        return $full_match;
    }
    
    return '';
}
```

### Example Output

**Before fix:**
```
| [31mThis is very long red text that     | status |
| wraps                                  |        |
```
(Second line loses red color)

**After fix:**
```
| [31mThis is very long red text that[0m  | status |
| [31mwraps[0m                             |        |
```
(Second line maintains red color with proper ANSI codes)

## Testing

### Manual Tests
Created comprehensive tests in `/tmp/test-colorize-wrap.php` and `/tmp/test-long-wrap.php` that verify:
- Basic wrapped colorized text preserves colors
- Multiple color codes in the same cell
- Mixed colorized and non-colorized columns
- Text that doesn't need wrapping (no regression)
- Very long text with multiple wraps

To run the tests:
```bash
php /tmp/test-colorize-wrap.php | cat -A
php /tmp/test-long-wrap.php | cat -A
php /tmp/test-colorize-comprehensive.php | cat -A
```

The output shows ANSI color codes (`^[[31m`, `^[[32m`, etc.) properly maintained across wrapped lines, with reset codes (`^[[0m`) at line breaks.

### Behat Test
Added test scenario in `features/formatter.feature` documenting the expected behavior.

## Next Steps

Since the fix is in the `wp-cli/php-cli-tools` library (a separate repository), the proper workflow is:

1. **Submit PR to wp-cli/php-cli-tools**
   - Create a fork of https://github.com/wp-cli/php-cli-tools
   - Apply the changes from this fix
   - Add tests to the php-cli-tools test suite
   - Submit PR with the fix

2. **Update wp-cli/wp-cli dependency**
   - Once php-cli-tools PR is merged and a new version is released
   - Update the version constraint in wp-cli/wp-cli's composer.json
   - Test that the fix works in the integrated environment

## Files Modified (in wp-cli/wp-cli)

- `features/formatter.feature`: Added test scenario for wrapped colorized lines

## Files To Be Modified (in wp-cli/php-cli-tools)

- `lib/cli/table/Ascii.php`:
  - Modified `row()` method to preserve colors when wrapping
  - Added `getLastActiveAnsiColor()` helper method

## Verification Commands

```bash
# Check syntax
php -l vendor/wp-cli/php-cli-tools/lib/cli/table/Ascii.php

# Run manual tests
php /tmp/test-long-wrap.php | cat -A

# Run code style check
composer phpcs -- vendor/wp-cli/php-cli-tools/lib/cli/table/Ascii.php
```

All verifications pass successfully.
