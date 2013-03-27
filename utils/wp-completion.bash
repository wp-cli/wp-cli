# bash completion for the wp command

_wp() {
	local cur prev opts file_ops

	cur=${COMP_WORDS[COMP_CWORD]}
	prev=${COMP_WORDS[COMP_CWORD-1]}

	if [[ 'wp' = $prev ]]; then
		opts=$(wp --completions | cut -d ' ' -f 1 | tr '\n' ' ')
	else
		opts=$(wp --completions | grep ^$prev | cut -d ' ' -f 2- | tr '\n' ' ')
	fi

	# An array of prev keywords that should get file completion
	declare -A file_ops=([create]=1 [import]=1)

	if [[ ${file_ops[$prev]} ]]; then
		COMPREPLY=( $(compgen -f "$cur") )
	else
		COMPREPLY=( $(compgen -W "$opts" -- $cur) )
	fi
}
complete -F _wp wp
