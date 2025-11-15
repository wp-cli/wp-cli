# tcsh completion for the `wp` command
#
# Installation:
# Source this file from your ~/.tcshrc:
#   source /FULL/PATH/TO/wp-completion.tcsh
#
# This completion script uses wp's built-in completion command to
# provide context-aware completions for all wp commands and options.

complete wp 'p@*@`wp cli completions --line="$COMMAND_LINE" --point=\`printf "%s" "$COMMAND_LINE" \| wc -c\` 2>/dev/null`@'
