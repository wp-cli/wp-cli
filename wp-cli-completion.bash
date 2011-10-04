# Very basic code completion
# 
# Code taken from here: http://www.debian-administration.org/article/317/An_introduction_to_bash_completion_part_2
_wp() {
	local cur prev opts
	COMPREPLY=()
	cur="${COMP_WORDS[COMP_CWORD]}"
	prev="${COMP_WORDS[COMP_CWORD-1]}"

	if [[ 'wp' = $prev ]]; then
		opts=$(wp --completions | cut -d ' ' -f 1 | tr '\n' ' ')
	else
		opts=$(wp --completions | grep ^$prev | cut -d ' ' -f 2- | tr '\n' ' ')
	fi

	COMPREPLY=( $(compgen -W "$opts" -- $cur) )
}
complete -F _wp wp
