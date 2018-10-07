# tweets.php

A command-line (CLI) script to batch-process and work with the files unzipped from the twitter backup archive zip file.  

## Features

- It can be used to generate 'grailbird' javascript files which are compatible with the default twitter archive viewer application, of which I have written an experimental updated version called [tweets-gb](https://github.com/vijinho/tweets-gb) where the generated files can be dropped-in and optionally linked via a **file:///** URL to the physical file on disk when browsed off-line, locally and in a web browser.
- Exported grailbird data can be viewed in [@vijinho/tweets-gb](https://github.com/vijinho/tweets-gb)
- It can also import grailbird files, join them, and optionally merge into existing tweets.js data.
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
- Save basic info of all users mentioned or RT'd to *users.json* with **--list-users**
- Adds new tweet attribute 'rt' if RT containing RT'd username

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
     --grailbird-import={dir} Import in data from the grailbird json files of the standard twitter export. If specified with '-a' will merge into existing tweets before outputting new file.
     --list                   Only list all files in export folder and halt - filename
     --list-js                Only List all javascript files in export folder and halt
     --list-images            Only list all image files in export folder and halt
     --list-videos            Only list all video files in export folder and halt
     --list-users             Only list all users in tweets, (default filename 'users.json') and halt
     --list-missing-media     List media URLs for which no local file exists and halt (implies --local)
     --organize-media         Organize local downloaded media, for example split folder into date/month subfolders
     --download-missing-media Download missing media (from --list-missing-media) and halt, e.g.. missing media files (implies --local)
     --list-profile-images    Only list users profile images, (in filename 'users.json') and halt
     --download-profile-images  WARNING: This can be a lot of users! Download profile images.
     --tweets-count           Only show the total number of tweets and halt
-i,  --tweets-file={tweet.js} Load tweets from different json input file instead of default twitter 'tweet.js'
-a,  --tweets-all             Get all tweets (further operations below will depend on this)
     --date-from              Filter tweets from date/time, see: https://secure.php.net/manual/en/function.strtotime.php
     --date-to                Filter tweets up-to date/time, see: https://secure.php.net/manual/en/function.strtotime.php
     --no-retweets            Drop re-tweets (RT's)
     --no-mentions            Drop tweets starting with mentions
     --urls-expand            Expand URLs where shortened and data available (offline) in tweet (new attribute: text)
-u,  --urls-resolve           Unshorten and dereference URLs in tweet (in new attribute: text) - implies --urls-expand
     --urls-check             Check every single target url (except for twitter.com and youtube.com) and update - implies --urls-resolve
     --urls-check-source      Check failed source urls - implies --urls-resolve
     --urls-check-force       Forcibly checks every single failed (numeric) source and target url and update - implies --urls-check
-o,  --offline                Do not go-online when performing tasks (only use local files for url resolution for example)
-l,  --local                  Fetch local file information (if available) (new attributes: images,videos,files)
     --media-prefix           Prefix to local media folder instead of direct file:// path, e.g. for serving via web and prefixing a URL path, implies --local
-x,  --delete                 DANGER! At own risk. Delete files where savings can occur (i.e. low-res videos of same video), run with -t to test only and show files
     --dupes                  List (or delete) duplicate files. Requires '-x/--delete' option to delete (will rename duplicated file from '{tweet_id}-{id}.{ext}' to '{id}.{ext}). Preview with '--test'!
     --keys-required=k1,k2,.  Returned tweets which MUST have all of the specified keys
-r,  --keys-remove=k1,k2,.    List of keys to remove from tweets, comma-separated (e.g. 'sizes,lang,source,id_str')
-k,  --keys-filter=k1,k2,.    List of keys to only show in output - comma, separated (e.g. id,created_at,text)
     --regexp='/<pattern>/i'  Filter tweet text on regular expression, i.e /(google)/i see https://secure.php.net/manual/en/function.preg-match.php
     --regexp-save=name       Save --regexp results in the tweet under the key 'regexps' using the key/id name given
```

## Usage Examples:

These are just a few examples included in the **--help** option, it's very flexible!

Report duplicate tweet media files and output to 'dupes.json':
        `php tweets-cli/tweets.php -fdupes.json --dupes`

Show total tweets in tweets file:
        `php tweets.php --tweets-count --verbose`

Write all users mentioned in tweets to file 'users.json':
        `php tweets.php --list-users --verbose`

Show javascript files in backup folder:
        `php tweets.php --list-js --verbose`

Resolve all URLs in 'tweet.js' file, writing output to 'tweet.json':
        `php tweets.php --tweets-all --urls-resolve --filename=tweet.json`

Resolve all URLs in 'tweet.js' file, writing output to grailbird files in 'grailbird' folder and also 'tweet.json':
        `php tweets.php --tweets-all --urls-resolve --filename=tweet.json --grailbird=grailbird`

Get tweets, only id, created and text keys:
        `php tweets.php -v -a -o -u --keys-filter=id,created_at,text`

Get tweets from 1 Jan 2017 to 'last friday':
        `php tweets.php -v -a -o -u --date-from '2017-01-01' --date-to='last friday'`

Filter tweet text on word 'hegemony' since last year
         `php tweets.php -v -a -o -u -l -x -ggrailbird --date-from='last year' --regexp='/(hegemony)/i' --regexp-save=hegemony`

Generate grailbird files with expanded/resolved URLs:
        `php tweets.php --tweets-all --verbose --urls-expand --urls-resolve --grailbird=grailbird`

Generate grailbird files with expanded/resolved URLs using offline saved url data - no fresh checking:
        `php tweets.php --tweets-all --verbose --offline --urls-expand --urls-resolve --grailbird=grailbird`

Generate grailbird files with expanded/resolved URLs using offline saved url data and using local file references where possible:
        `php tweets.php --tweets-all --verbose --offline --urls-expand --urls-resolve --local --grailbird=grailbird`

Generate grailbird files with expanded/resolved URLs using offline saved url data and using local file references, dropping retweets:
        `php tweets.php --tweets-all --verbose --offline --urls-expand --urls-resolve --local --no-retweets --grailbird=grailbird`

Delete duplicate tweet media files (will rename them from '{tweet_id}-{id}.{ext}' to '{id}.{ext})':
        `php tweets-cli/tweets.php --delete --dupes`

Extract the first couple of words of the tweet and name the saved regexp 'words':
        `tweets.php -v -a -o -u -l -x -ggrailbird --date-from='last year' --regexp='/^(?P<first>[a-zA-Z]+)\s+(?P<second>[a-zA-Z]+)/i' --regexp-save=words`

Import grailbird files from 'import/data/js/tweets':
        `php tweets.php --grailbird-import=import/data/js/tweets --verbose`

Import and merge grailbird files from 'import/data/js/tweets', fully-resolving links and local files:
        `php tweets-cli/tweets.php -a --grailbird=grailbird --grailbird-import=import/data/js/tweets -o -l -u --verbose`

List URLs for which there are missing local media files:
        `php tweets.php -a --list-missing-media --verbose`

Download files from URLs for which there are missing local media files:
        `php tweets.php -a --download-missing-media --verbose`

Organize 'tweet_media' folder into year/month subfolders:
        `php tweets-cli/tweets.php --organize-media`

Export only tweets which have the 'withheld in countries' key to export/grailbird folder:
        `php tweets-cli/tweets.php -d -a -u -o -itweet.json --grailbird=export/grailbird --keys-required='withheld_in_countries'`

Prefix the local media with to a URL path 'assets':
        `php cli/tweets.php --media-prefix='/assets'`

## Note

- *I have only tested it on MacOS* but it should work under Linux.
- This script is memory-hungry, I had to increase my limit to 512MB to handle 10 years and over 30,000 tweets.

## Re-constructing the folder structure from a standard (old) twitter year/month file export

Supposing `tweets.php` is in the folder 'cli' and you are running for a user 'euromoan'.

### Make the following folders:

```
euromoan/www/euromoan - this is the top-level folder of the un-zipped file (containing the twitter index.html file)
euromoan/profile_media
euromoan/tweet_media
euromoan/tweet_files
```

### Create the following files

In the euromoan folder, copying the data from the account `data/js/user_details.js` and from browsing the twitter page for the user:

`account.js`:

```
window.YTD.account.part0 = [{
        "account": {
            "email": "euromoan@example.com",
            "createdVia": "web",
            "username": "euromoan",
            "accountId": "816715694133964800",
            "createdAt": "2007-01-01T00:00:00.000Z",
            "accountDisplayName": "Mario Drago",
            "timeZone": "Basel, Switzerland"
        }
    }]
```

`profile.js`:

```
window.YTD.profile.part0 = [{
        "profile": {
            "description": {
                "bio": "Evil banker. #TBTJ untouchable Communist Head of ECB. I do whatever it takes to keep EU masses enslaved, enriching my cronies of the BIS, FSB, G30 etc PARODY!.",
                "website": "",
                "location": "Basel, Switzerland"
            },
            "avatarMediaUrl": "https://pbs.twimg.com/profile_images/986255258073657350/g8fvWiDX.jpg",
            "headerMediaUrl": "https://pbs.twimg.com/profile_banners/816715694133964800/1523976777"
        }
    }]
```

Save the URL images to files in `profile_media`

### Combine files to make the `tweet.js` file

#### Making a default `tweet.js` file

This will create the `tweet.js` similar to a full twitter backup download zip contains.

    `php cli/tweets.php --dir=euromoan --dir-output=euromoan --grailbird-import=euromoan/www/euromoan/data/js/tweets --filename=tweet.js --debug`

This will also make `users.json` and `urls.json` files containing the use and url information contained therein.

#### Resolve URLs when creating

After the previous step, you can make a `tweet.json` (note extension change - by default `tweeta.php` cli creates .json files) file with the un-shortened/resolved URLs:

    `php cli/tweets.php --dir=euromoan --dir-output=euromoan --filename=tweet.json -a -u --urls-check-source --debug`

#### Generate grailbird export data file using data from previous step

This will create the YYYY-MM.js files with the resolved URLs in a folder structure as with the original twitter download in `export/grailbird`.

    `php cli/tweets.php --dir=euromoan --dir-output=euromoan --tweets-file=tweet.json --filename=tweet.json -a -u --grailbird=euromoan/export/grailbird --debug`

#### Missing local `tweet_media` files

This will list the local `tweet_media` files that are missing and where they would be downloaded:

    `php cli/tweets.php --dir=euromoan --dir-output=euromoan -a -u --debug --list-missing-media --filename=missing.json -l`

To download:

    `php cli/tweets.php --dir=euromoan --dir-output=euromoan -a -u --debug --download-missing-media -l `

To organize the `tweet_media` files into subfolders:

    `php cli/tweets.php --dir=euromoan --dir-output=euromoan -a -u --debug --organize-media -l`

#### Generate locally viewable offline tweets linked to downloaded files

Files will be exported to `euromoan/export/grailbird` in the correct folder structure to overwrite/replace the original download or use as data files for [@vijinho/tweets-gb](https://github.com/vijinho/tweets-gb)

    `php cli/tweets.php --dir=euromoan --dir-output=euromoan -a -u -l --grailbird=euromoan/export/grailbird --debug`

#### Fully check all URLs

This will check/update the source and destination URLs (if they have been redirected/changed) unless they are twitter.com or www.youtube.com hosts.

    `php cli/tweets.php --dir=euromoan --dir-output=euromoan -a -u --urls-check-force --debug`

## To Do

- Reduce memory-usage!
- Work and process other files in the twitter backup fileset, e.g. for Twitter Moments
- Option to export/copy a tweet and all associated files
- Option to write filtered tweets to a different file formats, e.g. CSV or HTML
- Option to generate markdown .md files from tweets in subfolders, compatible with [grav](https://getgrav.org/)

## Project History

This was written after browsing [@mwichary/twitter-export-image-fill](https://github.com/mwichary/twitter-export-image-fill) and reading about [this issue](https://github.com/mwichary/twitter-export-image-fill/issues/10):

> "Twitter has two ways of getting an archive. One is the way you show. The second requires going to:
> **Settings and privacy > Your Twitter data > Download your Twitter data > Download data**
