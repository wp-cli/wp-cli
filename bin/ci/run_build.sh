#!/bin/bash

set -ex

vendor/bin/phpunit

vendor/bin/behat --format progress
