#!/bin/bash

set -ex

phpunit

behat --format progress
