# Testing System Config Feature

This document describes how to manually test the `/etc/wp-cli/config.yml` feature.

## Prerequisites

You need root access or sudo privileges to create files in `/etc/wp-cli/`.

## Test 1: Basic System Config Loading

1. Create the system config directory and file:
```bash
sudo mkdir -p /etc/wp-cli
sudo tee /etc/wp-cli/config.yml > /dev/null <<EOF
disabled_commands:
  - eval
EOF
```

2. Run WP-CLI with debug output:
```bash
wp --info --debug
```

3. Expected output should include:
```
Debug (bootstrap): Using system config: /etc/wp-cli/config.yml
```

4. Verify the command is disabled:
```bash
wp eval 'echo "test";'
```

Expected: Error message saying 'eval' command is disabled.

5. Clean up:
```bash
sudo rm -rf /etc/wp-cli
```

## Test 2: System Config with Aliases

1. Create system config with an alias:
```bash
sudo mkdir -p /etc/wp-cli
sudo tee /etc/wp-cli/config.yml > /dev/null <<EOF
@staging:
  ssh: user@staging.example.com/var/www
  path: public_html
EOF
```

2. List aliases:
```bash
wp cli alias list
```

3. Expected output should show the `@staging` alias.

4. Clean up:
```bash
sudo rm -rf /etc/wp-cli
```

## Test 3: Override Priority (System < User < Project)

1. Create system config:
```bash
sudo mkdir -p /etc/wp-cli
sudo tee /etc/wp-cli/config.yml > /dev/null <<EOF
color: false
@myalias:
  ssh: system@example.com
EOF
```

2. Create user config:
```bash
mkdir -p ~/.wp-cli
cat > ~/.wp-cli/config.yml <<EOF
color: true
@myalias:
  ssh: user@example.com
EOF
```

3. Create project config in a test directory:
```bash
mkdir -p /tmp/test-wp-cli
cd /tmp/test-wp-cli
cat > wp-cli.yml <<EOF
@myalias:
  ssh: project@example.com
EOF
```

4. Check the alias from different locations:
```bash
# From outside any project (should use user config)
cd ~
wp cli alias list

# From inside project (should use project config)
cd /tmp/test-wp-cli
wp cli alias list
```

5. Expected: The `@myalias` should show different SSH values based on the config precedence.

6. Clean up:
```bash
sudo rm -rf /etc/wp-cli
rm -rf ~/.wp-cli/config.yml
rm -rf /tmp/test-wp-cli
```

## Test 4: System Config with Required Files

1. Create a custom PHP file:
```bash
sudo mkdir -p /etc/wp-cli
sudo tee /etc/wp-cli/custom.php > /dev/null <<EOF
<?php
define( 'SYSTEM_LOADED', true );
EOF
```

2. Create system config that requires it:
```bash
sudo tee /etc/wp-cli/config.yml > /dev/null <<EOF
require:
  - /etc/wp-cli/custom.php
EOF
```

3. Test that the file is loaded:
```bash
wp eval 'var_export( defined("SYSTEM_LOADED") );'
```

Expected output: `true`

4. Clean up:
```bash
sudo rm -rf /etc/wp-cli
```

## Test 5: No System Config (Graceful Degradation)

1. Ensure no system config exists:
```bash
sudo rm -rf /etc/wp-cli
```

2. Run WP-CLI with debug:
```bash
wp --info --debug
```

3. Expected output should include:
```
Debug (bootstrap): No readable system config found
```

4. WP-CLI should work normally without errors.
