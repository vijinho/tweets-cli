# tweets.php

A command-line (CLI) script to batch-process and work with the files unzipped from the twitter backup archive zip file.  

## Features

- It can be used to generate 'grailbird' javascript files which are compatible with the default twitter archive viewer application, of which I have written an experimental updated version called [tweets-gb](https://github.com/vijinho/tweets-gb) where the generated files can be dropped-in and optionally linked via a **file:///** URL to the physical file on disk when browsed off-line, locally and in a web browser.
- It can unshorten all short-links and resolve all links fully (saving the results to *urls.json* for re-use on successive runs (it's a time-consuming process to check-links which can take hours!).  This can be used with the **--offline** option to speed-up subsequent processing further. Media entity attributes will be updated to reflect changes.
- Option **local** will check subfolders for content and add file and path information into the tweet under new attributes (videos, images, files).  Also these local files will be swapped-in for the remote-ones for viewing off-line and loading faster.
- The **--delete** option will delete lower-bitrate video files and keep the highest bitrate file if used with the local-files option **local*- Can filter tweets on date/time (from and to specific dates) using [PHP strtotime](https://secure.php.net/manual/en/function.strtotime.php) for flexible date/time format
- An option exists to also delete duplicate local tweet files.  These are files named 9999999999-XXXXXXXX.(jpg|png|mp4|...) and the resultant file will chop off the numeric tweet_id at the start of the filename (and dash) and just rename one of the duplicate files to XXXXXXXX.(jpg|png|mp4|...), deleting the rest.  The script will update the file and entity links to reflect the new filename.
- Option to filter results on a list of given attributes/keys **--keys-filter** and also to drop keys altogether from tweets with **--keys-remove**.
- Can filter tweets based on executing a [PHP regular-expression](https://secure.php.net/manual/en/function.preg-match.php) (and optionally save the regular expression results in the tweet as a new attribute **regexps**)
- Creates a new tweet attribute: **created_at_unixtime** which is the unixtime of the tweet.
- Creates a new tweet attribute: **text** which is the cleaned-up tweet-text after processing, also named to be compatible with default twitter export.
*.
- Option to specify previous output of batch processing as input with **--tweets-file**
- All processed tweets are saved to **output.json** by default but this can be changed with **--filename**
- Output can also be optionally changed to .txt or serialized php.
- Option to discard tweets which are mentions or retweets (**--no-retweets** and **--no-mentions**)
- Can just return a json file of either of the following: js/json files, images, videos or all files in the twitter backup folder.
- (Probably I missed some, but never mind...)

## Usage - CLI Options

This is intentionally written as a stand-alone self-contained command-line php script, hacked-together, written in a procedural style.  These are the command-line options available:

```
Usage: php tweets.php
Adds/Modifies/Removes/Views tweets from exported twitter archive. The modified tweet text is a new attribute: text
(Specifying any other unknown argument options will be ignored.)

        -h,  --help                   Display this help and exit
        -v,  --verbose                Run in verbose mode
        -d,  --debug                  Run in debug mode (implies also -v, --verbose)
        -t,  --test                   Run in test mode, show what would be done, NO filesystem changes.
             --dir={.}                Directory of unzipped twitter backup files (current dir if not specified)
             --dir-output={.}         Directory to output files in (default to -dir above)
             --format={json}          Output format for script data: txt|php|json (default)
        -f,  --filename={output.}     Filename for output data from operation, default is 'output.{--OUTPUT_FORMAT}'
        -g,  --grailbird={dir}        Generate json output files compatible with the standard twitter export feature to dir
             --list                   Only list all files in export folder and halt - filename
             --list-js                Only List all javascript files in export folder and halt
             --list-images            Only list all image files in export folder and halt
             --list-videos            Only list all video files in export folder and halt
        -i,  --tweets-file={tweet.js} Load tweets from different json input file instead of default twitter 'tweet.js'
             --tweets-count           Only show the total number of tweets
        -a,  --tweets-all             Get all tweets (further operations below will depend on this)
             --date-from              Filter tweets from date/time, see: https://secure.php.net/manual/en/function.strtotime.php
             --date-to                Filter tweets up-to date/time, see: https://secure.php.net/manual/en/function.strtotime.php 
             --no-retweets            Drop re-tweets (RT's)
             --no-mentions            Drop tweets starting with mentions
             --urls-expand            Expand URLs where shortened and data available (offline) in tweet (new attribute: text)
        -u,  --urls-resolve           Shorten and dereference URLs in tweet (in new attribute: text) - implies --urls-expand
        -o,  --offline                Do not go-online when performing tasks (only use local files for url resolution for example)
        -l,  --local                  Fetch local file information (if available) (new attributes: images,videos,files)
        -x,  --delete                 DANGER! At own risk. Delete files where savings can occur (i.e. low-res videos of same video), run with -t to test only and show files
             --dupes                  List (or delete) duplicate files. Requires '-x/--delete' option to delete (will rename duplicated file from '{tweet_id}-{id}.{ext}' to '{id}.{ext}). Preview with '--test'!
        -r,  --keys-remove=k1,k2,.    List of keys to remove from tweets, comma-separated (e.g. 'sizes,lang,source,id_str')
        -k,  --keys-filter=k1,k2,.    List of keys to only show in output - comma, separated (e.g. id,created_at,text)
             --regexp                 Filter tweet text on regular expression, i.e /(google)/i see https://secure.php.net/manual/en/function.preg-match.php
             --regexp-save            Save --regexp results in the tweet under the key 'regexps'
```

## Usage Examples:

These are just a few examples included in the **--help** option, it's very flexible!

Report duplicate tweet media files and output to 'dupes.json':
        `php tweets-cli/tweets.php -fdupes.json --dupes`
        
Show total tweets in tweets file:
        `php tweets.php --tweets-count --verbose`

Show javascript files in backup folder:
        `php tweets.php --list-js --debug`

Resolve all URLs in 'tweet.js' file, writing output to 'tweet.json':
        `php tweets.php --tweets-all --urls-resolve --filename=tweet.json`

Resolve all URLs in 'tweet.js' file, writing output to grailbird files in 'grailbird' folder and also 'tweet.json':
        `php tweets.php --tweets-all --urls-resolve --filename=tweet.json --grailbird=grailbird`

Get tweets, only id, created and text keys:
        `php tweets.php -v -a -o -u --keys-filter=id,created_at,text`

Get tweets from 1 Jan 2017 to 'last friday': 
        `php tweets.php -v -a -o -u --date-from '2017-01-01' --date-to='last friday'`

Filter tweet text on word 'hegemony' since last year: 
        `php tweets.php -v -a -o -u -l -x -ggrailbird --date-from='last year' --regexp='/(hegemony)/i' --regexp-save`

Generate grailbird files with expanded/resolved URLs:
        `php tweets.php --tweets-all --debug --urls-expand --urls-resolve --grailbird=grailbird`

Generate grailbird files with expanded/resolved URLs using offline saved url data - no fresh checking:
        `php tweets.php --tweets-all --debug --offline --urls-expand --urls-resolve --grailbird=grailbird`

Generate grailbird files with expanded/resolved URLs using offline saved url data and using local file references where possible:
        `php tweets.php --tweets-all --debug --offline --urls-expand --urls-resolve --local --grailbird=grailbird`

Generate grailbird files with expanded/resolved URLs using offline saved url data and using local file references, dropping retweets:
        `php tweets.php --tweets-all --debug --offline --urls-expand --urls-resolve --local --no-retweets --grailbird=grailbird`

Delete duplicate tweet media files (will rename them from '{tweet_id}-{id}.{ext}' to '{id}.{ext})':
        `php tweets-cli/tweets.php --delete --dupes`

## Note

- *I have only tested it on MacOS* but it should work under Linux.
- This script is memory-hungry, I had to increase my limit to 512MB to handle 10 years and over 30,000 tweets.

## To Do

- Reduce memory-usage
- Work and process other files in the twitter backup fileset, e.g. for Twitter Moments
- Option to export/copy a tweet and all associated files 
- Option to write filtered tweets to a different file formats, e.g. CSV or HTML
- Option to generate markdown .md files from tweets in subfolders, compatible with [grav](https://getgrav.org/)

## Project History

This was written after browsing [@mwichary/twitter-export-image-fill](https://github.com/mwichary/twitter-export-image-fill) and reading about [this issue](https://github.com/mwichary/twitter-export-image-fill/issues/10):

> "Twitter has two ways of getting an archive. One is the way you show. The second requires going to:
> **Settings and privacy > Your Twitter data > Download your Twitter data > Download data**

