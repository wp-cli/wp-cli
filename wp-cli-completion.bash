# Very basic code completion
# 
# Code taken from here: http://www.debian-administration.org/article/317/An_introduction_to_bash_completion_part_2
_wp() 
{
    local cur prev opts
    COMPREPLY=()
    cur="${COMP_WORDS[COMP_CWORD]}"
    prev="${COMP_WORDS[COMP_CWORD-1]}"
    opts="core plugin option theme home help"
    
    COMPREPLY=( $(compgen -W "${opts}" -- ${cur}) )
    return 0
}
complete -F _wp wp