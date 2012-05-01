usage: wp export --path=<export-path> --user=<username/id>
   or: wp export --path=/tmp/ --user=admin --post_type=post --start_date=2011-01-01 --end_date=2011-12-31

Required parameters:
	--path			Full Path to directory where WXR export files should be stored

Optional filters:
	--start_date       Export only posts new than this date in format YYYY-MM-DD
	--end_date         Export only posts older than this date in format YYYY-MM-DD
	--post_type        Export only posts with this post_type
	--author           Export only posts by this author
	--category         Export only posts in this category
	--post_status      Export only posts with this post_status
	--skip_comments    Don't export comments
