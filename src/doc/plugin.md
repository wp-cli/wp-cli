usage: wp plugin <sub-command> [<plugin-name>]
   or: wp plugin path [<plugin-name>] [--dir]
   or: wp plugin install <plugin-name> [--activate] [--dev] [--version=1.2.3]

Available sub-commands:
   status       display status of all installed plugins or of a particular plugin

   activate     activate a particular plugin

   deactivate   deactivate a particular plugin

   toggle       toggle activation state of a particular plugin

   path         print path to the plugin's file
      --dir        get the path to the closest parent directory

   install      install a plugin from wordpress.org or from a zip file
      --activate   activate the plugin after installing it
      --dev        install the development version
      --version    install a specific version

   update       update a plugin from wordpress.org
      --all        update all plugins from wordpress.org

   uninstall    run the uninstallation procedure for a plugin

   delete       delete a plugin
