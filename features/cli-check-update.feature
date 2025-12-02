Feature: Check for updates

  Scenario: Ignores updates with a higher PHP version requirement
    Given that HTTP requests to https://api.github.com/repos/wp-cli/wp-cli/releases?per_page=100 will respond with:
      """
      HTTP/1.1 200
      Content-Type: application/json

      [
        {
          "url": "https://api.github.com/repos/wp-cli/wp-cli/releases/169243978",
          "assets_url": "https://api.github.com/repos/wp-cli/wp-cli/releases/169243978/assets",
          "upload_url": "https://uploads.github.com/repos/wp-cli/wp-cli/releases/169243978/assets{?name,label}",
          "html_url": "https://github.com/wp-cli/wp-cli/releases/tag/v999.9.9",
          "id": 169243978,
          "node_id": "RE_kwDOACQFs84KFnVK",
          "tag_name": "v999.9.9",
          "target_commitish": "main",
          "name": "Version 999.9.9",
          "draft": false,
          "prerelease": false,
          "created_at": "2024-08-08T03:04:55Z",
          "published_at": "2024-08-08T03:51:13Z",
          "assets": [
            {
              "url": "https://api.github.com/repos/wp-cli/wp-cli/releases/assets/184590231",
              "id": 184590231,
              "node_id": "RA_kwDOACQFs84LAJ-X",
              "name": "wp-cli-999.9.9.phar",
              "label": null,
              "content_type": "application/octet-stream",
              "state": "uploaded",
              "size": 7048108,
              "download_count": 722639,
              "created_at": "2024-08-08T03:51:05Z",
              "updated_at": "2024-08-08T03:51:08Z",
              "browser_download_url": "https://github.com/wp-cli/wp-cli/releases/download/v999.9.9/wp-cli-999.9.9.phar"
            },
            {
              "url": "https://api.github.com/repos/wp-cli/wp-cli/releases/assets/184590231",
              "id": 184590231,
              "node_id": "RA_kwDOACQFs84LAJ-X",
              "name": "wp-cli-999.9.9.phar",
              "label": null,
              "content_type": "application/octet-stream",
              "state": "uploaded",
              "size": 7048108,
              "download_count": 722639,
              "created_at": "2024-08-08T03:51:05Z",
              "updated_at": "2024-08-08T03:51:08Z",
              "browser_download_url": "https://github.com/wp-cli/wp-cli/releases/download/v999.9.9/wp-cli-999.9.9.manifest.json"
            }
          ],
          "tarball_url": "https://api.github.com/repos/wp-cli/wp-cli/tarball/v999.9.9",
          "zipball_url": "https://api.github.com/repos/wp-cli/wp-cli/zipball/v999.9.9",
          "body": "- Allow manually dispatching tests workflow [[#5965](https://github.com/wp-cli/wp-cli/pull/5965)]\r\n- Add fish shell completion [[#5954](https://github.com/wp-cli/wp-cli/pull/5954)]\r\n- Add defaults and accepted values for runcommand() options in doc [[#5953](https://github.com/wp-cli/wp-cli/pull/5953)]\r\n- Address warnings with filenames ending in fullstop on Windows [[#5951](https://github.com/wp-cli/wp-cli/pull/5951)]\r\n- Fix unit tests [[#5950](https://github.com/wp-cli/wp-cli/pull/5950)]\r\n- Update copyright year in license [[#5942](https://github.com/wp-cli/wp-cli/pull/5942)]\r\n- Fix breaking multi-line CSV values on reading [[#5939](https://github.com/wp-cli/wp-cli/pull/5939)]\r\n- Fix broken Gutenberg test [[#5938](https://github.com/wp-cli/wp-cli/pull/5938)]\r\n- Update docker runner to resolve docker path using `/usr/bin/env` [[#5936](https://github.com/wp-cli/wp-cli/pull/5936)]\r\n- Fix `inherit` path in nested directory [[#5930](https://github.com/wp-cli/wp-cli/pull/5930)]\r\n- Minor docblock improvements [[#5929](https://github.com/wp-cli/wp-cli/pull/5929)]\r\n- Add Signup fetcher [[#5926](https://github.com/wp-cli/wp-cli/pull/5926)]\r\n- Ensure the alias has the leading `@` symbol when added [[#5924](https://github.com/wp-cli/wp-cli/pull/5924)]\r\n- Include any non default hook information in CompositeCommand [[#5921](https://github.com/wp-cli/wp-cli/pull/5921)]\r\n- Correct completion case when ends in = [[#5913](https://github.com/wp-cli/wp-cli/pull/5913)]\r\n- Docs: Fixes for inline comments [[#5912](https://github.com/wp-cli/wp-cli/pull/5912)]\r\n- Update Inline comments [[#5910](https://github.com/wp-cli/wp-cli/pull/5910)]\r\n- Add a real-world example for `wp cli has-command` [[#5908](https://github.com/wp-cli/wp-cli/pull/5908)]\r\n- Fix typos [[#5901](https://github.com/wp-cli/wp-cli/pull/5901)]\r\n- Avoid PHP deprecation notices in PHP 8.1.x [[#5899](https://github.com/wp-cli/wp-cli/pull/5899)]",
          "reactions": {
            "url": "https://api.github.com/repos/wp-cli/wp-cli/releases/169243978/reactions",
            "total_count": 9,
            "+1": 4,
            "-1": 0,
            "laugh": 0,
            "hooray": 1,
            "confused": 0,
            "heart": 0,
            "rocket": 4,
            "eyes": 0
          }
        },
        {
          "url": "https://api.github.com/repos/wp-cli/wp-cli/releases/169243978",
          "assets_url": "https://api.github.com/repos/wp-cli/wp-cli/releases/169243978/assets",
          "upload_url": "https://uploads.github.com/repos/wp-cli/wp-cli/releases/169243978/assets{?name,label}",
          "html_url": "https://github.com/wp-cli/wp-cli/releases/tag/v777.7.7",
          "id": 169243978,
          "node_id": "RE_kwDOACQFs84KFnVK",
          "tag_name": "v777.7.7",
          "target_commitish": "main",
          "name": "Version 777.7.7",
          "draft": false,
          "prerelease": false,
          "created_at": "2024-08-08T03:04:55Z",
          "published_at": "2024-08-08T03:51:13Z",
          "assets": [
            {
              "url": "https://api.github.com/repos/wp-cli/wp-cli/releases/assets/184590231",
              "id": 184590231,
              "node_id": "RA_kwDOACQFs84LAJ-X",
              "name": "wp-cli-777.7.7.phar",
              "label": null,
              "content_type": "application/octet-stream",
              "state": "uploaded",
              "size": 7048108,
              "download_count": 722639,
              "created_at": "2024-08-08T03:51:05Z",
              "updated_at": "2024-08-08T03:51:08Z",
              "browser_download_url": "https://github.com/wp-cli/wp-cli/releases/download/v777.7.7/wp-cli-777.7.7.phar"
            },
            {
              "url": "https://api.github.com/repos/wp-cli/wp-cli/releases/assets/184590231",
              "id": 184590231,
              "node_id": "RA_kwDOACQFs84LAJ-X",
              "name": "wp-cli-777.7.7.phar",
              "label": null,
              "content_type": "application/octet-stream",
              "state": "uploaded",
              "size": 7048108,
              "download_count": 722639,
              "created_at": "2024-08-08T03:51:05Z",
              "updated_at": "2024-08-08T03:51:08Z",
              "browser_download_url": "https://github.com/wp-cli/wp-cli/releases/download/v777.7.7/wp-cli-777.7.7.manifest.json"
            }
          ],
          "tarball_url": "https://api.github.com/repos/wp-cli/wp-cli/tarball/v777.7.7",
          "zipball_url": "https://api.github.com/repos/wp-cli/wp-cli/zipball/v777.7.7",
          "body": "- Allow manually dispatching tests workflow [[#5965](https://github.com/wp-cli/wp-cli/pull/5965)]\r\n- Add fish shell completion [[#5954](https://github.com/wp-cli/wp-cli/pull/5954)]\r\n- Add defaults and accepted values for runcommand() options in doc [[#5953](https://github.com/wp-cli/wp-cli/pull/5953)]\r\n- Address warnings with filenames ending in fullstop on Windows [[#5951](https://github.com/wp-cli/wp-cli/pull/5951)]\r\n- Fix unit tests [[#5950](https://github.com/wp-cli/wp-cli/pull/5950)]\r\n- Update copyright year in license [[#5942](https://github.com/wp-cli/wp-cli/pull/5942)]\r\n- Fix breaking multi-line CSV values on reading [[#5939](https://github.com/wp-cli/wp-cli/pull/5939)]\r\n- Fix broken Gutenberg test [[#5938](https://github.com/wp-cli/wp-cli/pull/5938)]\r\n- Update docker runner to resolve docker path using `/usr/bin/env` [[#5936](https://github.com/wp-cli/wp-cli/pull/5936)]\r\n- Fix `inherit` path in nested directory [[#5930](https://github.com/wp-cli/wp-cli/pull/5930)]\r\n- Minor docblock improvements [[#5929](https://github.com/wp-cli/wp-cli/pull/5929)]\r\n- Add Signup fetcher [[#5926](https://github.com/wp-cli/wp-cli/pull/5926)]\r\n- Ensure the alias has the leading `@` symbol when added [[#5924](https://github.com/wp-cli/wp-cli/pull/5924)]\r\n- Include any non default hook information in CompositeCommand [[#5921](https://github.com/wp-cli/wp-cli/pull/5921)]\r\n- Correct completion case when ends in = [[#5913](https://github.com/wp-cli/wp-cli/pull/5913)]\r\n- Docs: Fixes for inline comments [[#5912](https://github.com/wp-cli/wp-cli/pull/5912)]\r\n- Update Inline comments [[#5910](https://github.com/wp-cli/wp-cli/pull/5910)]\r\n- Add a real-world example for `wp cli has-command` [[#5908](https://github.com/wp-cli/wp-cli/pull/5908)]\r\n- Fix typos [[#5901](https://github.com/wp-cli/wp-cli/pull/5901)]\r\n- Avoid PHP deprecation notices in PHP 8.1.x [[#5899](https://github.com/wp-cli/wp-cli/pull/5899)]",
          "reactions": {
            "url": "https://api.github.com/repos/wp-cli/wp-cli/releases/169243978/reactions",
            "total_count": 9,
            "+1": 4,
            "-1": 0,
            "laugh": 0,
            "hooray": 1,
            "confused": 0,
            "heart": 0,
            "rocket": 4,
            "eyes": 0
          }
        }
      ]
      """

    And that HTTP requests to wp-cli-999.9.9.manifest.json will respond with:
      """
      HTTP/1.1 200
      Content-Type: application/json

      {
        "requires_php": "123.4.5"
      }
      """

    And that HTTP requests to wp-cli-777.7.7.manifest.json will respond with:
      """
      HTTP/1.1 200
      Content-Type: application/json

      {
        "requires_php": "5.6.0"
      }
      """

    When I run `wp cli check-update`
    Then STDOUT should be a table containing rows:
      | version | update_type | package_url | status | requires_php |
      | 999.9.9 | major | https://github.com/wp-cli/wp-cli/releases/download/v999.9.9/wp-cli-999.9.9.phar | unavailable | 123.4.5 |
      | 777.7.7 | major | https://github.com/wp-cli/wp-cli/releases/download/v777.7.7/wp-cli-777.7.7.phar | available | 5.6.0 |
