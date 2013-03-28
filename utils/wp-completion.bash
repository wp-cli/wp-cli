# bash completion for the wp command

_wp() {
	local cur prev opts

	cur=${COMP_WORDS[COMP_CWORD]}
	prev=${COMP_WORDS[COMP_CWORD-1]}

	if [[ 'wp' = $prev ]]; then
		opts=$(wp --completions | cut -d ' ' -f 1 | tr '\n' ' ')
	else
		opts=$(wp --completions | grep ^$prev | cut -d ' ' -f 2- | tr '\n' ' ')
	fi

	if [[ 'create' = $prev ]]; then
		COMPREPLY=( $(compgen -f "$cur") )
	else
		COMPREPLY=( $(compgen -W "$opts" -- $cur) )
	fi
}
complete -F _wp wp
