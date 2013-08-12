#!/bin/bash

set -ex

phpunit

php behat.phar --format progress
