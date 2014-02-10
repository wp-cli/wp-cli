# bash completion for the `wp` command

_wp_complete() {
	local cur=${COMP_WORDS[COMP_CWORD]}
	local opts=$(wp cli completions --line="$COMP_LINE" --point="$COMP_POINT")

	if [[ "$opts" = "<file>" ]]
	then
		COMPREPLY=( $(compgen -f -- $cur) )
	else
		COMPREPLY=( $(compgen -W "$opts" -- $cur) )
	fi
}
complete -F _wp_complete wp
