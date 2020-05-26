# bash completion for the `wp` command

_wp_complete() {
	local OLD_IFS="$IFS"
	local cur=${COMP_WORDS[COMP_CWORD]}
	local command=${COMP_WORDS[1]}
	local subcommand=${COMP_WORDS[2]}
 
	IFS=$'\n';  # want to preserve spaces at the end
	COMPREPLY=""

	if [[ "$command" = "plugin" ]]
	then
		case "$subcommand" in
		activate|deactivate|update|delete|get|is-active|path|status|uninstall|verify-checksums)
			local plugin_list=$(wp plugin list --field=name --format=csv)
			COMPREPLY=( $(compgen -W "${plugin_list}" -- ${cur} ))
			;;
		esac
	elif [[ "$command" = "theme" ]]
	then
		case "$subcommand" in
		activate|delete|disable|enable|is-active|is-installed|path|status|update)
			local theme_list=$(wp plugin list --field=name --format=csv)
			COMPREPLY=( $(compgen -W "${theme_list}" -- ${cur} ))
			;;
		esac
	fi
	if [[ "$COMPREPLY" = "" ]]
	then
		local opts="$(wp cli completions --line="$COMP_LINE" --point="$COMP_POINT")"

		if [[ "$opts" =~ \<file\>\s* ]]
		then
			COMPREPLY=( $(compgen -f -- $cur) )
		elif [[ $opts = "" ]]
		then
			COMPREPLY=( $(compgen -f -- $cur) )
		else
			COMPREPLY=( ${opts[*]} )
		fi
	fi
	
	IFS="$OLD_IFS"
	return 0
}
complete -o nospace -F _wp_complete wp
