usage: wp blog <sub-command> [options]

Available sub-commands:
   create   create a new blog
     --slug           Base for the new domain. Subdomain on subdomain installs, directory on subdirectory installs
     --title          Title of the new blog
     [--email]        Email for Admin user. User will be created if none exists. Assignement to Super Admin if not included
     [--site_id]      Site (network) to associate new blog with. Defaults to current site (typically 1)
     [--public]       Whether or not the new site is public (indexed)
