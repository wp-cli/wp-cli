# Visual Demonstration: Colorization Fix

## Problem Demonstration

Without the fix, colorized text loses its color on wrapped lines:

```
+-------------------------------------+
| name                                | status   |
+-------------------------------------+
| [RED]This is very long red text     | active   |  ← Color starts here
| that wraps                          |          |  ← Color lost!
+-------------------------------------+
```

The second line loses the red color because the ANSI escape sequence is not preserved.

## Solution Demonstration  

With the fix, colors are maintained across all wrapped lines:

```
+-------------------------------------+
| name                                | status   |
+-------------------------------------+
| [RED]This is very long red text[RST]| active   |  ← Color ends with reset
| [RED]that wraps[RST]                |          |  ← Color continues!
+-------------------------------------+
```

Each wrapped segment:
1. Starts with the active color code: `\033[31m` (red)
2. Ends with reset code: `\033[0m`

## Actual Output (with ANSI codes visible)

Running the test with `cat -A` to show ANSI codes:

```bash
$ php /tmp/test-long-wrap.php | cat -A
```

Output:
```
+-------------------------------------+----------+$
| name                                | status   |$
+-------------------------------------+----------+$
| ^[[31mThis is a very very very long ^[[0m      | active   |$
| ^[[31mred text that should definitel^[[0m      |          |$
| ^[[31my wrap across multiple lines t^[[0m      |          |$
| ^[[31mo test the color preservation ^[[0m      |          |$
| ^[[31mfeature^[[0m                             |          |$
| ^[[32mAnother extremely long green t^[[0m      | inactive |$
| ^[[32mext with lots and lots of word^[[0m      |          |$
| ^[[32ms that will surely wrap severa^[[0m      |          |$
| ^[[32ml times^[[0m                             |          |$
+-------------------------------------+----------+$
```

Where:
- `^[[31m` = Red color (ANSI: `\033[31m`)
- `^[[32m` = Green color (ANSI: `\033[32m`)
- `^[[0m` = Reset (ANSI: `\033[0m`)

## Key Observations

1. **Color Codes Present**: Every wrapped line segment has the proper color code
2. **Reset Codes Added**: Each segment ends with reset to prevent color bleeding
3. **Consistent Formatting**: The table structure remains intact
4. **No Breaking Changes**: Non-colorized content works exactly as before

## Terminal Display

When viewed in a color-capable terminal, the output appears as:

```
+-------------------------------------+----------+
| name                                | status   |
+-------------------------------------+----------+
| This is a very very very long       | active   |  ← Red text
| red text that should definitel      |          |  ← Still red!
| y wrap across multiple lines t      |          |  ← Still red!
| o test the color preservation       |          |  ← Still red!
| feature                             |          |  ← Still red!
| Another extremely long green t      | inactive |  ← Green text
| ext with lots and lots of word      |          |  ← Still green!
| s that will surely wrap severa      |          |  ← Still green!
| l times                             |          |  ← Still green!
+-------------------------------------+----------+
```

All wrapped continuation lines maintain their intended colors!

## Impact

This fix ensures that WP-CLI commands that use colorization in tables (like `wp package list`, plugin status displays, etc.) will properly maintain colors even when content wraps, improving readability and user experience.
