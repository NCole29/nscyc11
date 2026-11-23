#!/usr/bin/env bash
# Prints the test classes one CI shard runs, one path per line.
#
# Usage, from the Drupal web root:
#   shard-tests.sh <shard> <total> <module tests/src directory>
#
# PHPUnit lists the test cases, inherited tests and data sets included. Each
# class is weighted by its number of cases, a browser test costing about twice
# a kernel test (most of a kernel test is installing CiviCRM). Heaviest first,
# each class goes to the shard with the least work so far, so the shards
# finish at about the same time. Every shard computes the same split.
set -euo pipefail

shard=$1
total=$2
dir=$3

../vendor/bin/phpunit -c core --list-tests "$dir" \
  | sed -nE 's/^ - (Drupal\\Tests\\civicrm_entity\\[^:]+)::.*/\1/p' \
  | sort | uniq -c \
  | awk -v dir="$dir" '{
      path = $2
      sub(/^Drupal\\Tests\\civicrm_entity\\/, "", path)
      gsub(/\\/, "/", path)
      per_case = (path ~ /^Functional/) ? 20 : 9
      print $1 * per_case, dir "/" path ".php"
    }' \
  | sort -k1,1nr -k2,2 \
  | awk -v shard="$shard" -v total="$total" '
      {
        best = 1
        for (i = 2; i <= total; i++) {
          if (load[i] < load[best]) {
            best = i
          }
        }
        load[best] += $1
        if (best == shard) {
          print $2
        }
      }
    '
